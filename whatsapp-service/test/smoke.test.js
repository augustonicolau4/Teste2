import test from 'node:test';
import assert from 'node:assert/strict';
import { config } from '../src/config.js';
import { normalizeNumber } from '../src/evolution.js';

test('config carrega com valores por omissao e fica congelado', () => {
  assert.equal(typeof config.PORT, 'number');
  assert.ok(config.PORT > 0);
  assert.equal(config.DEFAULT_COUNTRY_CODE, '244');
  assert.equal(typeof config.LARAVEL_BASE_URL, 'string');
  assert.equal(config.LARAVEL_BASE_URL.endsWith('/'), false);
  assert.ok(Object.isFrozen(config));
});

test('normalizeNumber: numero local de 9 digitos (Angola) recebe indicativo 244', () => {
  assert.equal(normalizeNumber('923456789'), '244923456789');
  assert.equal(normalizeNumber(' 923-456-789 '), '244923456789');
});

test('normalizeNumber: numero local com zero a esquerda perde o zero', () => {
  assert.equal(normalizeNumber('0923456789'), '244923456789');
  assert.equal(normalizeNumber('0923 456 789'), '244923456789');
});

test('normalizeNumber: numero com indicativo mantem-se inalterado', () => {
  assert.equal(normalizeNumber('244923456789'), '244923456789');
  assert.equal(normalizeNumber('+244 923 456 789'), '244923456789');
});

test('normalizeNumber: prefixo internacional 00 e removido', () => {
  assert.equal(normalizeNumber('00244923456789'), '244923456789');
  assert.equal(normalizeNumber('00 244 923 456 789'), '244923456789');
});

test('normalizeNumber: entradas vazias devolvem string vazia', () => {
  assert.equal(normalizeNumber(''), '');
  assert.equal(normalizeNumber(null), '');
  assert.equal(normalizeNumber(undefined), '');
  assert.equal(normalizeNumber('abc'), '');
});
