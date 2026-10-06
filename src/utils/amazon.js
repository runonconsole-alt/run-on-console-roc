// Amazon Associates links. Every Amazon URL on the site should go through
// amazonAffiliateLink() so the store ID lives in exactly one place.

export const AMAZON_TAG = 'roc2602-20';
const AMAZON_ORIGIN = 'https://www.amazon.com';

// Matches /dp/ASIN, /gp/product/ASIN, /gp/aw/d/ASIN, /product/ASIN and /ASIN/ASIN
const ASIN_PATH = /\/(?:dp|gp\/product|gp\/aw\/d|product|ASIN)\/([A-Z0-9]{10})(?=[/?#]|$)/i;

const isAmazonHost = (host) =>
  /(^|\.)amazon\.(com|ca|com\.mx|com\.br|co\.uk|de|fr|it|es|nl|se|pl|com\.be|com\.tr|ae|sa|eg|in|sg|com\.au|co\.jp)$/i.test(host);

const parseUrl = (url) => {
  try {
    return new URL(url.trim());
  } catch {
    return null;
  }
};

// Returns the 10-character product code from any amazon.* product URL, or ''.
export const extractAsin = (url) => {
  const parsed = url && parseUrl(url);
  if (!parsed || !isAmazonHost(parsed.hostname)) return '';
  const match = parsed.pathname.match(ASIN_PATH);
  return match ? match[1].toUpperCase() : '';
};

export const amazonProductLink = (asin) => `${AMAZON_ORIGIN}/dp/${asin}?tag=${AMAZON_TAG}`;

export const amazonSearchLink = (query) =>
  `${AMAZON_ORIGIN}/s?k=${encodeURIComponent(query.trim())}&tag=${AMAZON_TAG}`;

// Best affiliate link for a product:
//   1. a product URL with an ASIN -> clean /dp/ASIN link with our tag
//   2. any other amazon.* URL     -> same URL with our tag swapped in
//   3. a non-Amazon URL (e.g. amzn.to short links) -> left untouched
//   4. no URL                     -> Amazon search for the product name
export const amazonAffiliateLink = (url, productName = '') => {
  const asin = extractAsin(url);
  if (asin) return amazonProductLink(asin);

  const parsed = url && parseUrl(url);
  if (parsed) {
    if (!isAmazonHost(parsed.hostname)) return url;
    parsed.searchParams.set('tag', AMAZON_TAG);
    return parsed.toString();
  }

  if (productName && productName.trim()) return amazonSearchLink(productName);
  return `${AMAZON_ORIGIN}/?tag=${AMAZON_TAG}`;
};
