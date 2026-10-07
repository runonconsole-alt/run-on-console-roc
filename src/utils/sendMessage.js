/**
 * Send a contact or write-for-us form to the server (/api/v1/messages.php).
 * The message is kept in CMS -> Messages and emailed to support@ / comments@.
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
    throw new Error(data.error || 'Your message could not be sent. Please email support@runonconsole.com instead.');
  }
  return data;
}
