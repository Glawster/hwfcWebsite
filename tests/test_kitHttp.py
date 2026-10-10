"""Real PHP HTTP pipeline and browser acceptance with a local sendmail sink.
Run: HWFC_TEST_PHP=php python3 tests/test_kitHttp.py
Requires Python Playwright and Chromium for browser cases.
"""
import copy
import http.cookiejar
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timedelta, timezone

ROOT = Path(__file__).resolve().parents[1]

class KitHttpTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix='hwfc-kit-http-')
        cls.private = Path(cls.temp.name)
        (cls.private / 'orders').mkdir(mode=0o700)
        cls.config = json.loads((ROOT / 'kit/config.example.json').read_text())
        cls.config['storageDir'] = str(cls.private / 'orders')
        cls.config['window']['opens'] = (datetime.now(timezone.utc)-timedelta(days=1)).isoformat(timespec='seconds')
        cls.config['window']['closes'] = (datetime.now(timezone.utc)+timedelta(days=1)).isoformat(timespec='seconds')
        cls.config_path = cls.private / 'config.json'
        cls.config_path.write_text(json.dumps(cls.config))
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        cls.url = f'http://127.0.0.1:{port}'
        env = dict(os.environ, HWFC_KIT_CONFIG=str(cls.config_path), HWFC_TEST_MAIL_DIR=str(cls.private))
        cls.log = (cls.private / 'server.log').open('w')
        cls.proc = subprocess.Popen([os.environ.get('HWFC_TEST_PHP','php'), '-d', f'sendmail_path=/usr/bin/python3 {ROOT / "tests/mailSink.py"}', '-d', f'session.save_path={cls.private}', '-S', f'127.0.0.1:{port}', '-t', str(ROOT)], env=env, stdout=cls.log, stderr=cls.log)
        for _ in range(100):
            try:
                urllib.request.urlopen(cls.url+'/kit/')
                return
            except (OSError, urllib.error.URLError):
                if cls.proc.poll() is not None:
                    cls.log.flush()
                    raise RuntimeError((cls.private/'server.log').read_text())
                time.sleep(.05)
        raise RuntimeError('PHP test server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.proc.terminate()
        cls.proc.wait(timeout=10)
        cls.log.close()
        cls.temp.cleanup()

    def setUp(self):
        self.config_path.write_text(json.dumps(self.config))
        for path in (self.private/'orders').glob('*'): path.unlink()
        for path in (self.private/'orders').glob('.*'):
            if path.name not in ('.','..') and path.is_file(): path.unlink()
        for name in ['messages.jsonl','fail']:
            (self.private/name).unlink(missing_ok=True)
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, data=None, url='/kit/'):
        request = urllib.request.Request(self.url+url, data=urllib.parse.urlencode(data).encode() if data else None)
        try:
            response = self.client.open(request)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read().decode()

    def input(self):
        status, html = self.request()
        self.assertEqual(200,status)
        return {'csrf':re.search(r'name="csrf" value="([^"]+)"',html)[1], 'action':'review','name':'Test Member','email':'member@example.invalid','phone':'07700 900123','human':'yes','fulfilment':'collection','claimFree':'yes','lines[0][product]':'playingShirt','lines[0][size]':'L','lines[0][quantity]':'2','lines[0][initials]':'AW','totalPence':'1','pricePence':'1','freeUnits':'2'}

    def review(self, data):
        status,html=self.request(data)
        self.assertEqual(200,status,html)
        self.assertIn('Check Your Order',html)
        return {'csrf':data['csrf'],'action':'confirm','review_token':re.search(r'name="review_token" value="([^"]+)"',html)[1]},html

    def test_acceptance_email_confirmation_and_duplicate_post(self):
        data=self.input()
        data.update({'fulfilment':'delivery','address1':'1 Test Street','city':'Test Town','postcode':'TEST','notes':'A safe note','accountHolder':'Other Account Holder'})
        confirm,review=self.review(data)
        self.assertIn('£36.00',review)
        self.assertIn('New member free shirt – £0.00',review)
        status,html=self.request(confirm)
        self.assertEqual(200,status)
        self.assertIn('Thank you – your kit order has been received',html)
        self.assertIn('£36.00',html)
        self.assertIn('as the payment reference',html)
        orders=list((self.private/'orders').glob('*.json'))
        self.assertEqual(1,len(orders))
        order=json.loads(orders[0].read_text())
        self.assertEqual(3600,order['totalPence'])
        self.assertEqual('test-autumn-2026',order['batchId'])
        self.assertEqual('sent',order['managerMail'])
        self.assertEqual('Other Account Holder',order['member']['accountHolder'])
        self.assertFalse(any('_' in key for key in order))
        self.assertFalse(any('_' in key for key in order['lines'][0]))
        messages=[json.loads(line) for line in (self.private/'messages.jsonl').read_text().splitlines()]
        self.assertEqual(2,len(messages))
        self.assertEqual('kit-manager@example.invalid',messages[0]['to'])
        for text in ['1 Test Street','Test Town','TEST','Other Account Holder','A safe note','AW','Payment Pending','test-autumn-2026','£36.00','New member free shirt']:
            self.assertIn(text,messages[0]['body'])
        self.request(confirm)
        self.assertEqual(1,len(list((self.private/'orders').glob('*.json'))))
        self.assertEqual(2,len((self.private/'messages.jsonl').read_text().splitlines()))

    def test_before_and_after_window_reject_real_post(self):
        data=self.input()
        for state in ['before','closed']:
            c=copy.deepcopy(self.config)
            now=datetime.now(timezone.utc)
            c['window']['opens']=(now+timedelta(days=1) if state=='before' else now-timedelta(days=2)).isoformat(timespec='seconds')
            c['window']['closes']=(now+timedelta(days=2) if state=='before' else now-timedelta(days=1)).isoformat(timespec='seconds')
            self.config_path.write_text(json.dumps(c))
            status,html=self.request()
            self.assertIn('Ordering opens' if state=='before' else 'window has closed',html)
            self.assertNotIn('id="kitForm"',html)
            status,html=self.request(data)
            self.assertEqual(422,status)
            self.assertEqual([],list((self.private/'orders').glob('*.json')))

    def test_window_closes_between_review_and_acceptance(self):
        confirm,_=self.review(self.input())
        c=copy.deepcopy(self.config)
        c['window']['closes']=(datetime.now(timezone.utc)-timedelta(seconds=1)).isoformat(timespec='seconds')
        self.config_path.write_text(json.dumps(c))
        status,html=self.request(confirm)
        self.assertEqual(422,status)
        self.assertIn('not open',html)
        self.assertEqual([],list((self.private/'orders').glob('*.json')))

    def test_mail_failure_is_saved_visible_and_retryable(self):
        (self.private/'fail').touch()
        confirm,_=self.review(self.input())
        status,html=self.request(confirm)
        self.assertIn('could not confirm the kit-manager email',html)
        self.assertIn('Please do not submit the order again',html)
        order=json.loads(next((self.private/'orders').glob('*.json')).read_text())
        self.assertEqual('failed',order['managerMail'])
        (self.private/'fail').unlink()
        proc=subprocess.run([os.environ.get('HWFC_TEST_PHP','php'),'-d',f'sendmail_path=/usr/bin/python3 {ROOT / "tests/mailSink.py"}',str(ROOT/'kit/retryNotifications.php')],env=dict(os.environ,HWFC_KIT_CONFIG=str(self.config_path),HWFC_TEST_MAIL_DIR=str(self.private)),capture_output=True,text=True)
        self.assertEqual(0,proc.returncode,proc.stderr)
        self.assertIn('manager=sent, member=sent',proc.stdout)
        self.assertEqual(404,self.request(url='/kit/retryNotifications.php')[0])

    def test_invalid_input_preserved_escaped_and_antibot(self):
        data=self.input()
        data['name']='<script>alert(1)</script>'
        for change in [{'human':''},{'website':'bot'},{'lines[0][size]':'BAD'},{'claimFree':'1'},{'lines[0][quantity]':'1.5'},{'csrf':'bad'},{'lines[0][shirtNumber]':'8'}]:
            status,html=self.request(dict(data,**change))
            self.assertEqual(422,status)
            self.assertNotIn('<script>alert(1)</script>',html)
        status,html=self.request(dict(data, email='invalid'))
        self.assertIn('&lt;script&gt;alert(1)&lt;/script&gt;',html)
        self.assertEqual([],list((self.private/'orders').glob('*.json')))

    def test_missing_configuration_fails_closed(self):
        self.config_path.write_text('{}')
        status,html=self.request()
        self.assertEqual(503,status)
        self.assertIn('currently unavailable',html)
        self.assertNotIn('id="kitForm"',html)

    def test_browser_workflow_and_viewport_matrix(self):
        from playwright.sync_api import sync_playwright
        with sync_playwright() as pw:
            browser=pw.chromium.launch(headless=True)
            for width,height in [(375,667),(390,844),(768,1024),(1280,800)]:
                page=browser.new_page(viewport={'width':width,'height':height})
                page.goto(self.url+'/kit/')
                self.assertIn('close:',page.locator('.infoStrip').first.inner_text())
                self.assertLessEqual(page.evaluate('document.documentElement.scrollWidth'),width)
                self.assertFalse(page.locator('#deliveryFields').is_visible())
                self.assertEqual(0,page.locator('.kitProduct').nth(1).locator('input[name$="[initials]"]').count())
                self.assertEqual(0,page.locator('input[name*="shirtNumber"]').count())
                page.locator('#quantity-0').fill('2')
                page.locator('#size-0').select_option('L')
                page.locator('#initials-0').fill('AW')
                page.locator('#claimFree').check()
                self.assertIn('£31.00',page.locator('#runningLines').inner_text())
                page.locator('#name').fill('Browser Member')
                page.locator('#email').fill('browser@example.invalid')
                page.locator('#phone').fill('07700 900123')
                page.locator('input[value="delivery"]').check()
                page.locator('#address1').fill('1 Browser Street')
                page.locator('#city').fill('Test City')
                page.locator('#postcode').fill('TEST')
                page.locator('#human').check()
                page.get_by_role('button',name='Review Kit Order',exact=True).click()
                self.assertIn('£36.00',page.locator('main').inner_text())
                page.get_by_role('button',name='Back / Change Order').click()
                self.assertEqual('Browser Member',page.locator('#name').input_value())
                page.get_by_role('button',name='Review Kit Order',exact=True).click()
                page.get_by_role('button',name='Submit Kit Order',exact=True).click()
                page.get_by_role('heading',name='Thank you – your kit order has been received').wait_for()
                self.assertIn('£36.00',page.locator('main').inner_text())
                page.close()
            browser.close()

if __name__=='__main__':
    unittest.main(verbosity=2)
