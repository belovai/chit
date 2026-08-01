import { useRouter, type RouteParamsRawGeneric } from 'vue-router'

/**
 * Zárási logika overlay-route-okhoz.
 *
 * Ha az előző history-bejegyzés a szülő route, `back()` — így nem nő a
 * history. Ha nem (deep link), `replace()` — így a vissza gomb nem nyitja
 * újra az overlayt.
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
