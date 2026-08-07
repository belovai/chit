import type { Ref } from 'vue'

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

export function useFocusTrap(containerRef: Ref<HTMLElement | null>) {
  let previouslyFocused: HTMLElement | null = null

  function focusableElements(): HTMLElement[] {
    const container = containerRef.value
    if (!container) return []
    return Array.from(container.querySelectorAll<HTMLElement>(FOCUSABLE))
  }

  function onKeydown(event: KeyboardEvent) {
    if (event.key !== 'Tab') return

    const elements = focusableElements()
    if (elements.length === 0) {
      event.preventDefault()
      return
    }

    const first = elements[0] as HTMLElement
    const last = elements[elements.length - 1] as HTMLElement
    const active = document.activeElement

    if (event.shiftKey && active === first) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && active === last) {
      event.preventDefault()
      first.focus()
    }
  }

  function activate() {
    previouslyFocused = document.activeElement as HTMLElement | null
    document.addEventListener('keydown', onKeydown)

    const elements = focusableElements()
    if (elements.length === 0) {
      containerRef.value?.focus()
      return
    }

    // For overlays containing a form, the first input field is the right entry
    // point — in DOM order the header's close button would be first, which is
    // a useless initial focus.
    const firstControl = elements.find((element) =>
      ['INPUT', 'SELECT', 'TEXTAREA'].includes(element.tagName),
    )

    ;(firstControl ?? (elements[0] as HTMLElement)).focus()
  }

  function deactivate() {
    document.removeEventListener('keydown', onKeydown)
    previouslyFocused?.focus()
    previouslyFocused = null
  }

  return { activate, deactivate }
}
