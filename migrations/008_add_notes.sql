-- Migration 008: add notes column to emails and update reminder template
ALTER TABLE emails
    ADD COLUMN notes TEXT NULL DEFAULT NULL;

-- Insert {{NOTES_BLOCK}} after the subject line in the reminder template
UPDATE email_templates
SET body = '<p style="font-size:15px;font-weight:700;color:#1a1a1a;margin:0 0 6px 0;">{{SUBJECT}}</p>\n{{NOTES_BLOCK}}\n<p style="font-size:13px;color:#888;margin:0 0 24px 0;">Here are your snooze options — pick one to reschedule:</p>\n<div style="margin-bottom:24px;">\n  {{SNOOZE_BUTTONS}}\n</div>\n<div style="border-top:1px solid #eee;padding-top:16px;">\n  <a href="{{CANCEL_URL}}" style="font-size:12px;color:#e74c3c;text-decoration:none;font-weight:600;">&#10005; Cancel this reminder</a>\n</div>',
    variables = '{{SUBJECT}}, {{NOTES_BLOCK}}, {{CANCEL_URL}}, {{SNOOZE_BUTTONS}}'
WHERE slug = 'reminder';
