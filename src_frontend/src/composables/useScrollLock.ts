let lockCount = 0
let previousOverflow = ''

export function useScrollLock() {
  function lock() {
    if (lockCount === 0) {
      previousOverflow = document.body.style.overflow
      document.body.style.overflow = 'hidden'
    }
    lockCount += 1
  }

  function unlock() {
    if (lockCount === 0) return
    lockCount -= 1
    if (lockCount === 0) {
      document.body.style.overflow = previousOverflow
    }
  }

  return { lock, unlock }
}
