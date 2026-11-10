import test from 'node:test';
import assert from 'node:assert/strict';
import {browserAdapter} from '../helpers/guest-dom.mjs';
const b=browserAdapter();await import('../../resources/js/guest-journey.js');
test('direct private hash URLs cannot render a dashboard, profile, settings or chat while signed out',()=>{for(const route of ['dashboard','dashboard/My%20Quotations','my-requests','my-quotations','my-chats','profile','settings','chat/invalid']){location.hash='#journey/'+route;assert.match(b.host.innerHTML,/Sign In Required/);assert.match(b.host.innerHTML,/Please sign in to access your requests, quotations, and messages/);assert.doesNotMatch(b.host.innerHTML,/gj-dashboard-brand/);}location.hash='#journey/new';assert.match(b.host.innerHTML,/Let’s begin your/);assert.doesNotMatch(b.host.innerHTML,/Sign In Required/);location.hash='#journey/home';});
