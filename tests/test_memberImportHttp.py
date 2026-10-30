"""Real PHP HTTP pipeline for the admin member import (REQ-004 import, REQ-006 admin page).
Run: HWFC_TEST_PHP=php python3 tests/test_memberImportHttp.py
Uses synthetic member data only.
"""
import csv
import html
import http.cookiejar
import io
import os
from pathlib import Path
import re
import socket
import sqlite3
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.request
import uuid
import zipfile
from xml.sax.saxutils import escape

ROOT = Path(__file__).resolve().parents[1]

HEADERS = [
    'Timestamp', 'Full name', 'Preferred name (if different)', 'Date of birth', 'Home address', 'Postcode',
    'Telephone number', 'Email address', 'Preferred method of contact', 'Emergency contact full name',
    'Relationship to you', 'Emergency contact telephone number', 'Second emergency contact name',
    'Second emergency contact telephone number',
    'Do you have a medical condition that may affect your participation or require assistance?',
    'If yes, what would you like us to know?',
    'Do you have any allergies that may require emergency assistance?',
    'If yes, please provide relevant allergy details.',
    'Do you carry emergency medication that we should know about?',
    'If yes, what is it and where is it kept during sessions?',
    'Do you have any mobility, balance, hearing or sight requirements we should accommodate?',
    'Is there anything else that would help us support you safely?',
    'Membership type', 'Would you like to receive club news and updates?',
    'Are you happy to be added to the club WhatsApp group?',
    'Can we use photographs or videos featuring you on our social media or website?',
    'Would you be interested in helping with club activities or events?',
    'I confirm that the information I have provided is accurate.',
    'I will inform the club if my emergency contacts or relevant health information change.',
    'I understand that walking football is a physical activity and that I should seek medical advice if I am unsure whether I can participate safely.',
    'I acknowledge that I have been provided with the club privacy notice.',
    'Email Address',
]


EXPORT_EXTRA = [
    'Membership start date', 'Membership ID', 'Status', 'Membership expiry date', 'Payment plan', 'Payment status',
    'Last payment date', 'Paid through date', 'Next payment due date', 'Second emergency contact relationship',
    'Application reference',
]


def member(full_name, email, dob_serial, **overrides):
    row = {
        'Timestamp': 46322.5, 'Full name': full_name, 'Date of birth': dob_serial,
        'Home address': '1 Test Street, Testtown', 'Postcode': 'bt00 1aa', 'Telephone number': '07000 000000',
        'Email address': email, 'Preferred method of contact': 'WhatsApp',
        'Emergency contact full name': 'Test Contact', 'Relationship to you': 'Friend',
        'Emergency contact telephone number': '07000 000001',
        HEADERS[14]: 'No', HEADERS[16]: 'No', HEADERS[18]: 'No', HEADERS[20]: 'No', HEADERS[21]: 'N/A',
        'Membership type': 'Playing member', HEADERS[23]: 'Yes', HEADERS[24]: 'Yes', HEADERS[25]: 'Yes',
        HEADERS[26]: 'Maybe', HEADERS[27]: 'I confirm', HEADERS[28]: 'I confirm', HEADERS[29]: 'I confirm',
        HEADERS[30]: 'I confirm',
    }
    row.update(overrides)
    return [row.get(heading, '') for heading in HEADERS]


ROWS = [
    member('Alex Example', 'Alex@Example.TEST', 20000, **{
        HEADERS[14]: 'Yes', HEADERS[15]: 'Test condition <b>\nSecond line', HEADERS[18]: 'Yes', HEADERS[19]: 'Test inhaler\nIn kit bag',
        'Preferred method of contact': 'Text message',
        'Can we use photographs or videos featuring you on our social media or website?': 'Please ask me first',
    }),
    member('Sam Van OHara', 'sam@example.test', 21000, **{'Second emergency contact name': 'Second Contact', 'Second emergency contact telephone number': '07000 000002'}),
    member('Alex Example', 'alex@example.test', 20000),
    member('Robin Missing', 'robin@ example.test', ''),
]


def column_letters(index):
    letters = ''
    index += 1
    while index:
        index, remainder = divmod(index - 1, 26)
        letters = chr(65 + remainder) + letters
    return letters


def build_xlsx(rows):
    strings = []

    def cell(ref, value):
        if value == '':
            return ''
        if isinstance(value, (int, float)):
            return f'<c r="{ref}"><v>{value}</v></c>'
        strings.append(value)
        return f'<c r="{ref}" t="s"><v>{len(strings) - 1}</v></c>'

    sheet_rows = []
    for row_index, row in enumerate([HEADERS] + rows, start=1):
        cells = ''.join(cell(f'{column_letters(i)}{row_index}', value) for i, value in enumerate(row))
        sheet_rows.append(f'<row r="{row_index}">{cells}</row>')
    shared = ''.join(f'<si><t xml:space="preserve">{escape(s)}</t></si>' for s in strings)
    main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
    rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, 'w', zipfile.ZIP_DEFLATED) as archive:
        archive.writestr('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>')
        archive.writestr('xl/workbook.xml', f'<?xml version="1.0"?><workbook xmlns="{main}" xmlns:r="{rel}"><sheets><sheet name="Form Responses 1" sheetId="1" r:id="rId7"/></sheets></workbook>')
        archive.writestr('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Target="worksheets/sheet1.xml" Id="rId7" Type="worksheet"/></Relationships>')
        archive.writestr('xl/sharedStrings.xml', f'<?xml version="1.0"?><sst xmlns="{main}">{shared}</sst>')
        archive.writestr('xl/worksheets/sheet1.xml', f'<?xml version="1.0"?><worksheet xmlns="{main}"><sheetData>{"".join(sheet_rows)}</sheetData></worksheet>')
    return buffer.getvalue()


def build_csv(rows, start_dates=None):
    out = io.StringIO()
    writer = csv.writer(out)
    writer.writerow(HEADERS + (['Membership start date'] if start_dates else []))
    for index, row in enumerate(rows):
        values = list(row) + ([start_dates[index]] if start_dates else [])
        values[0] = '27/10/2026 12:00:00'
        values[3] = '15/03/1955' if values[3] != '' else ''
        writer.writerow(values)
    return ('﻿' + out.getvalue()).encode('utf-8')


class MemberImportHttpTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix='hwfc-member-import-')
        cls.private = Path(cls.temp.name)
        cls.db_path = cls.private / 'members.sqlite'
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        cls.url = f'http://127.0.0.1:{port}'
        env = dict(os.environ, HWFC_MEMBER_DB=str(cls.db_path), HWFC_ADMIN_DEV_MODE='1')
        cls.log = (cls.private / 'server.log').open('w')
        cls.proc = subprocess.Popen([os.environ.get('HWFC_TEST_PHP', 'php'), '-d', f'session.save_path={cls.private}', '-S', f'127.0.0.1:{port}', '-t', str(ROOT)], env=env, stdout=cls.log, stderr=cls.log)
        for _ in range(100):
            try:
                urllib.request.urlopen(cls.url + '/admin/memberImport.php')
                return
            except (OSError, urllib.error.URLError):
                if cls.proc.poll() is not None:
                    cls.log.flush()
                    raise RuntimeError((cls.private / 'server.log').read_text())
                time.sleep(.05)
        raise RuntimeError('PHP test server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.proc.terminate()
        cls.proc.wait(timeout=10)
        cls.log.close()
        cls.temp.cleanup()

    def setUp(self):
        if self.db_path.exists():
            with sqlite3.connect(self.db_path) as db:
                db.execute('DELETE FROM membership_audit')
                db.execute('DELETE FROM membership_applications')
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def get(self, path='/admin/memberImport.php'):
        with self.opener.open(self.url + path) as response:
            return html.unescape(response.read().decode())

    def csrf(self):
        return re.search(r'name="csrf" value="([^"]+)"', self.get()).group(1)

    def post(self, fields, file_name=None, content=b''):
        boundary = uuid.uuid4().hex
        body = b''
        for name, value in (fields.items() if isinstance(fields, dict) else fields):
            body += f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode()
        if file_name is not None:
            body += f'--{boundary}\r\nContent-Disposition: form-data; name="memberSpreadsheet"; filename="{file_name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode() + content + b'\r\n'
        body += f'--{boundary}--\r\n'.encode()
        request = urllib.request.Request(self.url + '/admin/memberImport.php', data=body, headers={'Content-Type': f'multipart/form-data; boundary={boundary}'})
        with self.opener.open(request) as response:
            return html.unescape(response.read().decode())

    def upload(self, content, file_name='Member Responses.xlsx', default_start='', update=False):
        fields = {'csrf': self.csrf(), 'action': 'preview', 'defaultStartDate': default_start}
        if update:
            fields['updateExisting'] = '1'
        return self.post(fields, file_name, content)

    def commit(self, rows=None):
        page = self.get()
        if rows is None:
            rows = re.findall(r'name="rows\[\]" value="(\d+)"', page)
        csrf = re.search(r'name="csrf" value="([^"]+)"', page).group(1)
        return self.post([('csrf', csrf), ('action', 'commit')] + [('rows[]', str(row)) for row in rows])

    def accept(self, email, membership_id):
        with sqlite3.connect(self.db_path) as db:
            db.execute("UPDATE membership_applications SET status = 'Active', membership_id = ? WHERE email = ?", (membership_id, email))

    def audit(self, event):
        with sqlite3.connect(self.db_path) as db:
            return [row[0] for row in db.execute('SELECT details FROM membership_audit WHERE event_type = ?', (event,))]

    def members(self):
        if not self.db_path.exists():
            return {}
        with sqlite3.connect(self.db_path) as db:
            db.row_factory = sqlite3.Row
            return {r['email']: dict(r) for r in db.execute('SELECT * FROM membership_applications')}

    def test_xlsx_preview_commit_and_reimport_is_non_destructive(self):
        page = self.upload(build_xlsx(ROWS), default_start='2026-11-01')
        self.assertIn('Apply <span data-selected-count>3</span> Selected Rows', page)
        self.assertIn('alex@example.test', page)
        self.assertNotIn('Alex@Example.TEST', page)
        self.assertIn('Repeats spreadsheet row 2.', page)
        self.assertIn('Date of birth is missing; 01/01/1970 will be used until it is amended.', page)
        self.assertNotIn('Test condition', page)
        self.assertEqual(self.members(), {})

        page = self.commit()
        self.assertIn('3 new member records imported as Pending. 0 existing member records updated.', page)
        members = self.members()
        self.assertEqual(set(members), {'alex@example.test', 'sam@example.test', 'robin@example.test'})
        self.assertEqual(members['robin@example.test']['date_of_birth'], '1970-01-01')
        self.assertIn('01/01/1970 is a placeholder', members['robin@example.test']['admin_notes'])
        alex, sam = members['alex@example.test'], members['sam@example.test']
        self.assertEqual((alex['first_name'], alex['last_name'], alex['status']), ('Alex', 'Example', 'Pending'))
        self.assertEqual((sam['first_name'], sam['last_name']), ('Sam Van', "O'Hara"))
        self.assertEqual((alex['membership_start_date'], alex['membership_expiry_date']), ('2026-11-01', '2027-10-31'))
        self.assertEqual(alex['date_of_birth'], '1954-10-03')
        self.assertEqual(alex['postcode'], 'BT00 1AA')
        self.assertEqual(alex['preferred_contact'], 'text')
        self.assertEqual(alex['photo_consent'], 0)
        self.assertEqual(sam['photo_consent'], 1)
        self.assertEqual(alex['health_relevant'], 1)
        self.assertEqual(alex['health_details'], 'Medical condition: Test condition <b>\nSecond line\nEmergency medication: Test inhaler\nIn kit bag')
        self.assertEqual((sam['health_relevant'], sam['health_details']), (0, None))
        self.assertIn('Membership type: Playing member', alex['admin_notes'])
        self.assertIn('Photographs or videos answer: Please ask me first', alex['admin_notes'])
        self.assertEqual(sam['second_emergency_name'], 'Second Contact')
        self.assertIsNone(alex['membership_id'])
        with sqlite3.connect(self.db_path) as db:
            self.assertEqual(db.execute("SELECT count(*) FROM membership_audit WHERE event_type = 'member_imported'").fetchone()[0], 3)

        with sqlite3.connect(self.db_path) as db:
            db.execute("UPDATE membership_applications SET phone = 'kept' WHERE email = 'alex@example.test'")
        page = self.upload(build_xlsx(ROWS))
        self.assertNotIn('Selected Row', page)
        self.assertIn('Matches existing member', page)
        self.assertEqual(self.members()['alex@example.test']['phone'], 'kept')

    def test_csv_download_is_accepted(self):
        self.upload(build_csv(ROWS[:2], ['01/12/2026', '']), 'Member Responses - Form Responses 1.csv', default_start='2026-11-01')
        self.commit()
        members = self.members()
        self.assertEqual(len(members), 2)
        self.assertEqual(members['alex@example.test']['membership_start_date'], '2026-12-01')
        self.assertEqual(members['sam@example.test']['membership_start_date'], '2026-11-01')
        self.assertEqual(members['sam@example.test']['date_of_birth'], '1955-03-15')

    def test_wrong_template_and_cancel_change_nothing(self):
        page = self.upload(b'Name,Email\nA Person,a@example.test\n', 'other.csv')
        self.assertIn('does not match the member responses template', page)
        self.upload(build_xlsx(ROWS))
        page = self.post({'csrf': self.csrf(), 'action': 'cancel'})
        self.assertIn('No member records were changed.', page)
        self.assertEqual(self.members(), {})

    def export(self, file_name=None, with_disposition=False):
        fields = {'csrf': self.csrf(), 'action': 'export'}
        if file_name is not None:
            fields['exportFileName'] = file_name
        boundary = uuid.uuid4().hex
        body = ''.join(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n' for k, v in fields.items()) + f'--{boundary}--\r\n'
        request = urllib.request.Request(self.url + '/admin/memberImport.php', data=body.encode(), headers={'Content-Type': f'multipart/form-data; boundary={boundary}'})
        with self.opener.open(request) as response:
            self.assertEqual(response.headers.get_content_type(), 'text/csv')
            self.assertIn('attachment;', response.headers['Content-Disposition'])
            content = response.read()
            return (content, response.headers['Content-Disposition']) if with_disposition else content

    @staticmethod
    def rows_of(content):
        rows = list(csv.reader(io.StringIO(content.decode('utf-8-sig'))))
        return rows[0], [dict(zip(rows[0], row)) for row in rows[1:]]

    @staticmethod
    def to_csv(heading, records):
        out = io.StringIO()
        writer = csv.writer(out)
        writer.writerow(heading)
        for record in records:
            writer.writerow([record[h] for h in heading])
        return out.getvalue().encode()

    def test_export_matches_form_layout_and_round_trips(self):
        self.upload(build_xlsx(ROWS), default_start='2026-11-01')
        self.commit()
        with sqlite3.connect(self.db_path) as db:
            db.execute("UPDATE membership_applications SET address = '=HYPERLINK(\"x\")' WHERE email = 'sam@example.test'")
            db.execute("INSERT INTO membership_applications (application_uuid, first_name, last_name, date_of_birth, address, postcode, email, phone, preferred_contact, emergency_name, emergency_relationship, emergency_phone, health_relevant, health_details, privacy_version, submitted_at, updated_at, status) VALUES ('a'||hex(randomblob(15)), 'Web', 'Applicant', '1950-01-02', 'Addr', 'BT1', 'web@example.test', '+44 7000 000003', 'email', 'C', 'R', '1', 1, 'Free text from the website form', 'v', '2026-10-28T10:00:00+00:00', '2026-10-28T10:00:00+00:00', 'Active')")
            db.execute("INSERT INTO membership_applications (application_uuid, first_name, last_name, date_of_birth, address, postcode, email, phone, preferred_contact, emergency_name, emergency_relationship, emergency_phone, privacy_version, submitted_at, updated_at, status) VALUES ('b'||hex(randomblob(15)), 'Rejected', 'Person', '1950-01-02', 'Addr', 'BT1', 'rejected@example.test', '1', 'email', 'C', 'R', '1', 'v', '2026-10-28T10:00:00+00:00', '2026-10-28T10:00:00+00:00', 'Rejected')")
        content = self.export()
        rows = list(csv.reader(io.StringIO(content.decode('utf-8-sig'))))
        self.assertEqual(rows[0], HEADERS + EXPORT_EXTRA)
        by_email = {row[7]: dict(zip(rows[0], row)) for row in rows[1:]}
        self.assertEqual(set(by_email), {'alex@example.test', 'sam@example.test', 'robin@example.test', 'web@example.test'})
        alex = by_email['alex@example.test']
        self.assertEqual(alex['Full name'], 'Alex Example')
        self.assertEqual(alex['Membership start date'], '01/11/2026')
        self.assertEqual(by_email['web@example.test']['Membership start date'], '')
        self.assertEqual(alex['Date of birth'], '03/10/1954')
        self.assertEqual(alex['Preferred method of contact'], 'Text message')
        self.assertEqual((alex[HEADERS[14]], alex[HEADERS[15]]), ('Yes', 'Test condition <b>\nSecond line'))
        self.assertEqual((alex[HEADERS[18]], alex[HEADERS[19]]), ('Yes', 'Test inhaler\nIn kit bag'))
        self.assertEqual(alex[HEADERS[25]], 'Please ask me first')
        self.assertEqual((alex['Membership type'], alex[HEADERS[26]]), ('Playing member', 'Maybe'))
        self.assertEqual(alex[HEADERS[30]], '')
        self.assertEqual(by_email['sam@example.test']['Home address'], '\'=HYPERLINK("x")')
        web = by_email['web@example.test']
        self.assertEqual(web['Telephone number'], '+44 7000 000003')
        self.assertEqual((web[HEADERS[14]], web[HEADERS[15]]), ('Yes', 'Free text from the website form'))

        page = self.upload(content, 'export.csv')
        self.assertIn('New Members', page)
        self.assertNotIn('Selected Row', page)

        before = self.members()
        with sqlite3.connect(self.db_path) as db:
            db.execute('DELETE FROM membership_applications')
        self.upload(content, 'export.csv')
        self.commit()
        after = self.members()
        compared = ['first_name', 'last_name', 'preferred_name', 'date_of_birth', 'address', 'postcode', 'phone', 'preferred_contact',
                    'emergency_name', 'emergency_relationship', 'emergency_phone', 'second_emergency_name', 'second_emergency_phone',
                    'health_relevant', 'health_details', 'photo_consent', 'submitted_at', 'membership_start_date', 'membership_expiry_date']
        for email in ('alex@example.test', 'sam@example.test'):
            self.assertEqual({k: after[email][k] for k in compared}, {k: before[email][k] for k in compared}, email)
        self.assertEqual(after['web@example.test']['health_details'], 'Medical condition: Free text from the website form')

    def import_and_accept(self):
        self.upload(build_xlsx(ROWS), default_start='2026-11-01')
        self.commit()
        self.accept('alex@example.test', 'HWFC-000001')
        self.accept('sam@example.test', 'HWFC-000002')

    def test_unedited_export_has_no_updates(self):
        self.import_and_accept()
        page = self.upload(self.export(), 'export.csv', update=True)
        self.assertEqual(re.findall(r'<span class="adminStatus">(\w+)</span>', page).count('Unchanged'), 2)
        self.assertNotIn('Selected Row', page)

    def test_update_mode_previews_changes_and_applies_selected_rows(self):
        self.import_and_accept()
        heading, records = self.rows_of(self.export())
        by_id = {r['Membership ID']: r for r in records}
        alex, sam = by_id['HWFC-000001'], by_id['HWFC-000002']
        alex.update({'Home address': '2 New Road', 'Email address': 'Alex.New@Example.test', 'Payment plan': 'annual',
                     'Payment status': 'Paid In Full', 'Paid through date': '31/10/2027', 'Postcode': '',
                     'Second emergency contact relationship': 'Sister', 'Status': 'Lapsed', 'Next payment due date': '01/01/2030',
                     HEADERS[18]: 'No', HEADERS[19]: ''})
        sam['Home address'] = '3 Other Road'
        content = self.to_csv(heading, records)

        page = self.upload(content, 'edited.csv')
        self.assertIn('Matches existing member HWFC-000001', page)
        self.assertNotIn('Selected Row', page)

        page = self.upload(content, 'edited.csv', update=True)
        self.assertIn('Home address:</strong> 1 Test Street, Testtown → 2 New Road', page)
        self.assertIn('Email address:</strong> alex@example.test → alex.new@example.test', page)
        self.assertIn('Health information:</strong> Previous → Changed', page)
        self.assertIn('Status is not changed by import', page)
        self.assertNotIn('Test inhaler', page)
        self.assertNotIn('Postcode:', page)
        self.assertIn('Check All', page)
        self.assertIn('Uncheck All', page)

        alex_row = re.search(r'name="rows\[\]" value="(\d+)"[^>]*>\s*</td>\s*<td>\d+</td>\s*<td>Alex', page).group(1)
        page = self.commit([alex_row])
        self.assertIn('0 new member records imported as Pending. 1 existing member record updated.', page)
        members = self.members()
        updated = members['alex.new@example.test']
        self.assertEqual((updated['address'], updated['postcode'], updated['status']), ('2 New Road', 'BT00 1AA', 'Active'))
        self.assertEqual((updated['payment_frequency'], updated['payment_status']), ('Annual', 'Paid In Full'))
        self.assertEqual((updated['paid_through_date'], updated['next_payment_due_date']), ('2027-10-31', '2027-11-01'))
        self.assertEqual(updated['second_emergency_relationship'], 'Sister')
        self.assertEqual(updated['health_details'], 'Medical condition: Test condition <b>\nSecond line')
        self.assertEqual(members['sam@example.test']['address'], '1 Test Street, Testtown')
        details = self.audit('member_import_updated')
        self.assertEqual(len(details), 1)
        self.assertIn('"Home address"', details[0])
        self.assertNotIn('New Road', details[0])

    def test_update_mode_rejects_bad_rows_and_stale_previews(self):
        self.import_and_accept()
        heading, records = self.rows_of(self.export())
        by_id = {r['Membership ID']: r for r in records}
        by_id['HWFC-000001']['Home address'] = '2 New Road'
        by_id['HWFC-000002']['Payment plan'] = 'Weekly'
        robin = next(r for r in records if r['Email address'] == 'robin@example.test')
        robin.update({'Membership ID': 'HWFC-999999'})
        page = self.upload(self.to_csv(heading, records), 'edited.csv', update=True)
        self.assertIn('Payment plan must be one of: Not Set, Annual, Monthly.', page)
        self.assertIn('Membership ID HWFC-999999 was not found.', page)

        with sqlite3.connect(self.db_path) as db:
            db.execute("UPDATE membership_applications SET updated_at = 'later', phone = 'edited on website' WHERE membership_id = 'HWFC-000001'")
        page = self.commit()
        self.assertIn('1 row was skipped because the register changed after the preview', page)
        self.assertEqual(self.members()['alex@example.test']['address'], '1 Test Street, Testtown')

        self.upload(build_xlsx(ROWS))
        page = self.commit([])
        self.assertIn('Select at least one row to import or update.', page)

    def test_update_mode_refuses_payment_changes_for_pending_members(self):
        self.upload(build_xlsx(ROWS[:1]))
        self.commit()
        with sqlite3.connect(self.db_path) as db:
            db.execute("UPDATE membership_applications SET membership_id = 'HWFC-000005'")
        heading, records = self.rows_of(self.export())
        records[0]['Payment status'] = 'Waived'
        page = self.upload(self.to_csv(heading, records), 'edited.csv', update=True)
        self.assertIn('Payment details can only be changed for an active member.', page)

    def test_export_file_name(self):
        _, disposition = self.export(with_disposition=True)
        self.assertRegex(disposition, r'filename="\d{8} HWFC Member Export\.csv"')
        _, disposition = self.export('Committee copy "v2"/../x.csv', with_disposition=True)
        self.assertIn('filename="Committee copy v2..x.csv"', disposition)

    def test_invalid_csrf_is_rejected(self):
        self.csrf()
        page = self.post({'csrf': 'wrong', 'action': 'preview'}, 'Member Responses.xlsx', build_xlsx(ROWS))
        self.assertIn('Invalid admin form session.', page)
        self.assertEqual(self.members(), {})


if __name__ == '__main__':
    unittest.main()
