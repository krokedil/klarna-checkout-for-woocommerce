// Shared helper to parse PLUGIN_META_JSON plus a tiny safeGet accessor.
// Domain-specific derivations live in the scripts that consume metadata.

function loadMeta({ requireEnv = true } = {}) {
  const raw = process.env.PLUGIN_META_JSON;
  if (requireEnv && !raw) {
    throw new Error('PLUGIN_META_JSON env not set');
  }
  if (!raw) return {};
  try {
    return JSON.parse(raw);
  } catch (e) {
    throw new Error('Failed to parse PLUGIN_META_JSON: ' + e.message);
  }
}

/**
 * Safe deep getter.
 * safeGet(obj, 'a.b.c', defaultVal)
 * safeGet(obj, ['a','b','c'], defaultVal)
 */
function safeGet(obj, path, defaultValue) {
  if (!obj) return defaultValue;
  const segments = Array.isArray(path) ? path : String(path).split('.');
  let cur = obj;
  for (const seg of segments) {
    if (cur && Object.prototype.hasOwnProperty.call(cur, seg)) {
      cur = cur[seg];
    } else {
      return defaultValue;
    }
  }
  return cur === undefined ? defaultValue : cur;
}

// Assert that a required field exists (non-null / non-empty string) and return it.
function assertField(obj, path, message) {
  const val = safeGet(obj, path, undefined);
  const missing = val === undefined || val === null || (typeof val === 'string' && val.trim() === '');
  if (missing) {
    throw new Error(message || `Required metadata field missing: ${Array.isArray(path) ? path.join('.') : path}`);
  }
  return val;
}

// Assert multiple fields; returns an object mapping path -> value (paths as given)
function assertFields(obj, paths) {
  if (!Array.isArray(paths)) throw new Error('assertFields expects an array of paths');
  const result = {};
  for (const p of paths) {
    result[p] = assertField(obj, p);
  }
  return result;
}

module.exports = { loadMeta, safeGet, assertField, assertFields };
