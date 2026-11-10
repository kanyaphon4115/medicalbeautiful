import test from 'node:test';
import assert from 'node:assert/strict';
import {translateText} from '../../resources/js/i18n.js';
import thai from '../../resources/js/translations-th.js';
test('Thai translates key website and settings labels',()=>{for(const label of ['My Requests','Account Settings','Website language','Medical Information','Customer Support','Submit My Request'])assert.equal(translateText(label,'th'),thai[label]);});
test('English and uncatalogued content remain unchanged',()=>{assert.equal(translateText('My Requests','en'),'My Requests');assert.equal(translateText('Customer-authored medical note','th'),'Customer-authored medical note');assert.equal(translateText('user@example.com','th'),'user@example.com');});
test('Pagination and greeting translate without changing values',()=>{const english='Showing 1'+String.fromCharCode(8211)+'5 of 13 requests';const translated=translateText(english,'th');assert.notEqual(translated,english);assert.ok(translated.includes('13'));assert.ok(translated.includes('1'));assert.notEqual(translateText('Hello, TestUser','th'),'Hello, TestUser');assert.ok(translateText('Hello, TestUser','th').endsWith('TestUser'));});
test('Whitespace remains intact around translated text',()=>{assert.equal(translateText('  Sign In  ','th'),'  '+thai['Sign In']+'  ');});
