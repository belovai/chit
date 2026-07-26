// crypto.randomUUID() requires a secure context (https, or exactly "localhost"/"127.0.0.1")
// and throws on origins like http://chit.127.0.0.1.nip.io. These ids are only used as Vue
// :key values / DOM id suffixes, not for anything security-sensitive, so a plain random
// string is enough and avoids that secure-context landmine entirely.
export function randomId(): string {
  return Math.random().toString(36).slice(2) + Date.now().toString(36)
}
