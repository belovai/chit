// Namespace carries the transaction-domain error codes the backend returns
// (`transaction.not_found`); view copy lives in `transactions.ts`.
//
// `not_found` is raised by ExistsForOwnerRule, which guards the merchant and
// product references on a transaction form - not the transaction itself - so
// the copy has to read correctly under any of those fields.
export default {
  not_found: 'This selection no longer exists.',
  location_not_found: 'This location does not belong to the selected merchant.',
}
