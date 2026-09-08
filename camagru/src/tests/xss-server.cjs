// Test-only server: node camagru/src/tests/xss-server.cjs
// Open http://127.0.0.1:8793/ ; no database or external mail access.
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
http.createServer((req, res) => {
    const pathname = new URL(req.url, 'http://localhost').pathname;
    let file = null;
    if (pathname === '/') file = path.join(__dirname, 'xss-dom.html');
    else if (pathname === '/pagination') file = path.join(__dirname, 'pagination-dom.html');
    else if (pathname === '/fixture') file = path.join(root, 'app/Views/gallery.php');
    else if (/^\/js\/(?:pages\/)?[a-z-]+\.js$/.test(pathname)) file = path.join(root, 'public', pathname);
    if (!file || !fs.existsSync(file)) { res.writeHead(404); res.end(); return; }
    res.setHeader('Content-Type', pathname.endsWith('.js') ? 'text/javascript; charset=UTF-8' : 'text/html; charset=UTF-8');
    res.end(fs.readFileSync(file));
}).listen(8793, '127.0.0.1');
