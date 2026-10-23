/**
 * Send a website form to the server (/api/v1/messages.php): contact, support, write_for_us, suggestion, comment.
 * The message is kept in CMS -> Messages, emailed to the matching mailbox (contact@, support@, suggestions@,
 * comments@) and a copy goes to the sender.
 * Resolves to { reference } or throws an Error with a message to show the visitor.
 */
export async function sendMessage({ kind, name, email, subject = '', message, meta = {}, website = '' }) {
  let resp;
  try {
    resp = await fetch('/api/v1/messages.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ kind, name, email, subject, message, meta, website }),
    });
  } catch (e) {
    throw new Error('No connection. Check your internet and try again.');
  }
  const data = await resp.json().catch(() => ({}));
  if (!resp.ok || !data.success) {
    throw new Error(data.error || 'Your message could not be sent. Please email contact@runonconsole.com instead.');
  }
  return data;
}
