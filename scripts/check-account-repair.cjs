const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const parser = require('@babel/parser');
let count = 0;
function visit(dir) {
  for (const item of fs.readdirSync(dir, {withFileTypes:true})) {
    const p = path.join(dir,item.name);
    if(item.isDirectory()) visit(p);
    else if(/\.(jsx|js)$/.test(p)) {
      parser.parse(fs.readFileSync(p,'utf8'),{sourceType:'module',plugins:['jsx']}); count++;
    }
  }
}
visit('src');
const read = p => fs.readFileSync(p,'utf8');
const auth = read('public/api/v1/auth.php');
const runtime = read('public/api/v1/account-runtime.php');
const view = read('src/components/AuthView.jsx');
assert(auth.includes("'verify-email-code'"));
assert(auth.includes('rocEstablishSession'));
assert(auth.includes('email_verified=1,is_verified=1'));
assert(auth.includes('FOR UPDATE'));
assert(!view.includes('resendVerificationEmail('));
assert(runtime.includes("WHERE status='pending'"));
assert(runtime.includes('locked_by=?'));
assert(!runtime.includes('verify_peer'));
assert(read('src/context/AppContext.jsx').includes('[isSessionLoading, setIsSessionLoading] = useState(true)'));
assert(read('public/api/v1/oauth/google-token.php').includes('openssl_verify'));
console.log(`PASS: ${count} JS/JSX files parsed; static account-contract checks passed.`);
console.log('These are syntax/static checks, NOT PHP, database, browser, OAuth or delivery tests.');
