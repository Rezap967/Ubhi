import fs from 'fs';

const path = "resources/js/components/views/produkmenu/MerekView.vue";
const text = fs.readFileSync(path, "utf8");
const start = text.indexOf('<transition name="fade">');
const end = text.indexOf('</transition>', start);

if (start < 0 || end < 0) {
    console.log('transition block not found');
    process.exit(1);
}

const inner = text.slice(start, end);
const re = /<\/?([a-zA-Z0-9_-]+)([^>]*)>/g;
const selfClosing = new Set(['input', 'br', 'img', 'meta', 'link', 'area', 'base', 'col', 'embed', 'hr', 'param', 'source', 'track', 'wbr']);
let stack = [];
let m;

while ((m = re.exec(inner))) {
    const full = m[0];
    const tag = m[1];
    const isClose = full.startsWith('</');
    const attrs = m[2];
    const line = inner.slice(0, m.index).split('\n').length;

    if (selfClosing.has(tag.toLowerCase())) {
        continue;
    }

    if (isClose) {
        const top = stack[stack.length - 1];
        console.log(`${line}: close </${tag}> expected </${top}> stack=[${stack.join(', ')}]`);

        if (!top) {
            break;
        }

        if (top === tag) {
            stack.pop();
        } else {
            break;
        }
    } else {
        const closingSlash = attrs.trim().endsWith('/');
        console.log(`${line}: open <${tag}>${closingSlash?' self':' '}`);

        if (!closingSlash) {
            stack.push(tag);
        }
    }
}

console.log('FINAL stack:', stack.join(', '));