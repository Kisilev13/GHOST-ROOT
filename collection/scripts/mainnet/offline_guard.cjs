// Test-process guard only. No SDK, keys, network, or transaction signing allowed.
const { syncBuiltinESMExports } = require('node:module');
const audit = { networkAttempts: 0, signingAttempts: 0 };
globalThis.__ghostOfflineAudit = audit;
const denyNetwork = () => { audit.networkAttempts++; throw new Error('OFFLINE: network forbidden'); };
const denySigning = () => { audit.signingAttempts++; throw new Error('OFFLINE: signing forbidden'); };
globalThis.fetch = denyNetwork;
for (const name of ['node:http', 'node:https']) {
  const mod = require(name); mod.request = denyNetwork; mod.get = denyNetwork;
}
const socket = require('node:net').Socket.prototype;
const originalConnect = socket.connect;
socket.connect = function (...args) {
  // tsx uses local Unix-domain IPC; this cannot contact an internet host.
  const options = Array.isArray(args[0]) ? args[0][0] : args[0];
  const path = typeof options === 'object' ? options?.path : options;
  if (typeof path === 'string' && path.startsWith('/')) return originalConnect.apply(this, args);
  return denyNetwork();
};
require('node:tls').connect = denyNetwork;
require('node:dgram').createSocket = denyNetwork;
const crypto = require('node:crypto');
crypto.sign = denySigning; crypto.createSign = denySigning;
crypto.webcrypto.subtle.sign = denySigning;
syncBuiltinESMExports();
