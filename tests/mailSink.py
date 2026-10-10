"""External sendmail boundary for local tests; never sends network email."""
import json
import os
import sys
from email import message_from_string
from pathlib import Path

root = Path(os.environ['HWFC_TEST_MAIL_DIR'])
message = message_from_string(sys.stdin.read())
with (root / 'messages.jsonl').open('a') as sink:
    sink.write(json.dumps({'to': message.get('To'), 'subject': message.get('Subject'), 'body': message.get_payload(), 'from': message.get('From')}) + '\n')
if (root / 'fail').exists():
    sys.exit(1)
