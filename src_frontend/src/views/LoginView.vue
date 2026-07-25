<script lang="ts">
import { defineComponent } from 'vue'
import { mapActions, mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import FormField from '@/components/ui/FormField.vue'
import logoUrl from '@/assets/logo.svg'
import { useAuthStore } from '@/stores/auth'
import { isValidEmail } from '@/utils/validators'
import { translateErrorCode } from '@/utils/errors'

export default defineComponent({
  name: 'LoginView',

  components: {
    AppButton,
    FormField,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      logoUrl,
      form: {
        email: '',
        password: '',
      },
    }
  },

  computed: {
    ...mapState(useAuthStore, ['isLoading', 'fieldErrors', 'generalError']),

    clientErrorCodes(): Record<string, string[]> {
      const errors: Record<string, string[]> = {}
      if (this.form.email && !isValidEmail(this.form.email)) {
        errors.email = ['email']
      }
      if (this.form.password && this.form.password.length < 1) {
        errors.password = ['required']
      }
      return errors
    },

    isFormValid(): boolean {
      return (
        this.form.email.length > 0 &&
        this.form.password.length > 0 &&
        Object.keys(this.clientErrorCodes).length === 0
      )
    },

    generalErrorText(): string {
      return this.generalError ? translateErrorCode(this.t, this.generalError) : ''
    },
  },

  methods: {
    ...mapActions(useAuthStore, ['login']),

    fieldErrorsFor(field: string): string[] {
      const codes = this.fieldErrors[field] ?? this.clientErrorCodes[field] ?? []
      return codes.map((code) => translateErrorCode(this.t, code, field))
    },

    async onSubmit() {
      if (!this.isFormValid) return
      try {
        await this.login({ email: this.form.email, password: this.form.password })
        await this.$router.push({ name: 'dashboard' })
      } catch {
        // errors already captured in auth.fieldErrors / auth.generalError
      }
    },
  },
})
</script>

<template>
  <main class="flex min-h-screen items-center justify-center px-4">
    <div class="relative w-full max-w-sm border border-divider bg-surface p-6">
      <img :src="logoUrl" alt="Chit" class="absolute top-6 right-6 h-6 w-auto" />

      <h1 class="text-2xl mb-1">{{ t('auth.login.title') }}</h1>
      <p class="text-sm text-neutral-600 mb-6">{{ t('auth.login.subtitle') }}</p>

      <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
        <FormField
          id="email"
          v-model="form.email"
          :label="t('auth.emailLabel')"
          type="email"
          autocomplete="username"
          :errors="fieldErrorsFor('email')"
        />
        <FormField
          id="password"
          v-model="form.password"
          :label="t('auth.passwordLabel')"
          type="password"
          autocomplete="current-password"
          :errors="fieldErrorsFor('password')"
        />

        <p v-if="generalErrorText" class="text-sm text-danger-700">{{ generalErrorText }}</p>

        <AppButton type="submit" :disabled="isLoading || !isFormValid">
          {{ isLoading ? t('auth.login.submitting') : t('auth.login.submit') }}
        </AppButton>
      </form>

      <p class="mt-6 text-sm text-neutral-600">
        {{ t('auth.login.noAccount') }}
        <RouterLink :to="{ name: 'register' }" class="text-accent hover:text-accent-600">{{
          t('auth.login.registerLink')
        }}</RouterLink>
      </p>
    </div>
  </main>
</template>
