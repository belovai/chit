// Namespace also carries the auth-domain error codes the backend returns
// (`auth.invalid_credentials`, `auth.password_too_short`) alongside view copy,
// since a namespaced error code resolves as a direct translation key.
export default {
  login: {
    title: 'Sign in',
    subtitle: 'Sign in to your Chit account.',
    submit: 'Sign in',
    submitting: 'Signing in…',
    noAccount: "Don't have an account?",
    registerLink: 'Register',
  },
  register: {
    title: 'Register',
    subtitle: 'Create a new Chit account.',
    nameLabel: 'Name',
    submit: 'Register',
    submitting: 'Registering…',
    hasAccount: 'Already have an account?',
    loginLink: 'Sign in',
  },
  emailLabel: 'Email',
  passwordLabel: 'Password',
  invalid_credentials: 'These credentials do not match our records.',
  password_too_short: 'Password must be at least 6 characters.',
}
