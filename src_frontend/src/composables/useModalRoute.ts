import { useRouter, type RouteParamsRawGeneric } from 'vue-router'

/**
 * Close logic for overlay routes.
 *
 * If the previous history entry is the parent route, `back()` — so history
 * doesn't grow. If not (deep link), `replace()` — so the back button doesn't
 * reopen the overlay.
 */
export function useModalRoute(parentRouteName: string, parentParams: RouteParamsRawGeneric = {}) {
  const router = useRouter()

  function close() {
    const parent = router.resolve({ name: parentRouteName, params: parentParams })
    const back = router.options.history.state.back

    if (typeof back === 'string' && back === parent.fullPath) {
      router.back()
      return
    }

    router.replace({ name: parentRouteName, params: parentParams })
  }

  return { close }
}
