import fs from 'fs';

const path = 'resources/js/components/views/produkmenu/MerekView.vue';
const text = fs.readFileSync(path, 'utf8');
const start = text.indexOf('<template>');
const end = text.lastIndexOf('</template>');
const inner = text.slice(start + '<template>'.length, end);
const lines = inner.split('\n');
const selfClosing = new Set(['input', 'br', 'img', 'meta', 'link', 'area', 'base', 'col', 'embed', 'hr', 'param', 'source', 'track', 'wbr']);
let stack = [];

for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const re = /<\/?([A-Za-z0-9_-]+)([^>]*)>/g;
    let m;

    while ((m = re.exec(line)) !== null) {
        const full = m[0];
        const tag = m[1];
        const closing = full.startsWith('</');

        if (selfClosing.has(tag.toLowerCase())) {
            continue;
        }

        const attrs = m[2];

        if (closing) {
            const top = stack.length ? stack[stack.length - 1] : null;

            if (top !== tag) {
                console.log(`LINE ${i+1}: close </${tag}> expected </${top}> stack=[${stack.join(', ')}]`);
                console.log('RAW LINE:', line);
                process.exit(0);
            }

            stack.pop();
        } else {
            const selfClose = attrs.trim().endsWith('/');

            if (!selfClose) {
                stack.push(tag);
            }
        }
    }
}

console.log('END STACK', stack.slice(-20).join(', '));