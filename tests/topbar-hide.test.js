const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const SCRIPT = path.join(__dirname, '..', 'js', 'topbar-hide.js');

function runScript(topBar) {
  const handlers = {};
  const window = {
    scrollY: 0,
    addEventListener(event, callback) { handlers[event] = callback; },
  };
  const document = {
    addEventListener(event, callback) { handlers[event] = callback; },
    getElementById(id) {
      assert.equal(id, 'iwd-top-bar');
      return topBar;
    },
  };
  vm.runInNewContext(fs.readFileSync(SCRIPT, 'utf8'), { document, window });
  handlers.DOMContentLoaded();
  return {
    hasScrollHandler: () => typeof handlers.scroll === 'function',
    scroll(y) {
      window.scrollY = y;
      handlers.scroll();
    },
  };
}

test('legacy top bar hides after 100px and reappears only at page top', () => {
  const classes = new Set();
  const topBar = {
    classList: {
      add(name) { classes.add(name); },
      remove(name) { classes.delete(name); },
    },
  };
  const page = runScript(topBar);
  assert.equal(page.hasScrollHandler(), true);
  page.scroll(100);
  assert.equal(classes.has('hidden'), false);
  page.scroll(101);
  assert.equal(classes.has('hidden'), true);
  page.scroll(40);
  assert.equal(classes.has('hidden'), true);
  page.scroll(0);
  assert.equal(classes.has('hidden'), false);
});

test('without the legacy ID there is no scroll handler or side effect', () => {
  const page = runScript(null);
  assert.equal(page.hasScrollHandler(), false);
});
