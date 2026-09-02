import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    createEntry,
    hashPin,
    randomHex,
    verifyPin,
    sha256Hex,
} from '../../resources/js/lib/applock-crypto.ts';

test('createEntry produces a non-plaintext salted hash', async () => {
    const entry = await createEntry('1234');
    assert.ok(entry.salt.length >= 16 * 2, 'salt is a sufficiently long hex');
    assert.ok(entry.hash.length === 64, 'hash is a SHA-256 hex digest (64 chars)');
    assert.notEqual(entry.hash, '1234', 'plain PIN is never stored');
    assert.notEqual(entry.hash, entry.salt, 'hash differs from salt');
});

test('correct PIN verifies against its own entry', async () => {
    const entry = await createEntry('4829');
    assert.equal(await verifyPin(entry, '4829'), true);
});

test('wrong PIN does not verify', async () => {
    const entry = await createEntry('4829');
    assert.equal(await verifyPin(entry, '0000'), false);
    assert.equal(await verifyPin(entry, '48290'), false);
});

test('same PIN with different salts produces different hashes (defends against rainbow lookups)', async () => {
    const a = await createEntry('7777');
    const b = await createEntry('7777');
    assert.notEqual(a.salt, b.salt);
    assert.notEqual(a.hash, b.hash);
});

test('hashPin is deterministic for a fixed salt and pin', async () => {
    const salt = 'aabbccdd';
    assert.equal(await hashPin(salt, '1234'), await hashPin(salt, '1234'));
});

test('verifyPin rejects an empty pin and a mismatched salt/pin', async () => {
    const entry = await createEntry('2468');
    assert.equal(await verifyPin(entry, ''), false);
});

test('randomHex returns unique random hex of the requested byte length', () => {
    const a = randomHex(16);
    const b = randomHex(16);
    assert.equal(a.length, 32);
    assert.equal(b.length, 32);
    assert.notEqual(a, b);
    assert.match(a, /^[0-9a-f]+$/);
});

test('sha256Hex output matches a known vector', async () => {
    // SHA-256('hello') known digest.
    const digest = await sha256Hex('hello');
    assert.equal(
        digest,
        '2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824',
    );
});
