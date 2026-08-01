<script lang="ts">
import { defineComponent } from 'vue'
import { mapActions, mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import FormField from '@/components/ui/FormField.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppCard from '@/components/ui/AppCard.vue'
import logoUrl from '@/assets/logo.svg'
import { useAuthStore } from '@/stores/auth'
import { isValidEmail } from '@/utils/validators'
import { translateErrorCode } from '@/utils/errors'

export default defineComponent({
  name: 'LoginView',

  components: {
    AppButton,
    FormField,
    AppInput,
    AppCard,
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
  <main class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm">
      <img :src="logoUrl" alt="Chit" class="mx-auto mb-6 h-8 w-auto" />

      <AppCard>
        <h1 class="mb-1 text-xl">{{ t('auth.login.title') }}</h1>
        <p class="mb-6 text-[13px] text-neutral-600">{{ t('auth.login.subtitle') }}</p>

        <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
          <FormField
            id="email"
            v-slot="{ describedBy }"
            :label="t('auth.emailLabel')"
            :errors="fieldErrorsFor('email')"
          >
            <AppInput
              id="email"
              v-model="form.email"
              type="email"
              autocomplete="username"
              :invalid="fieldErrorsFor('email').length > 0"
              :aria-describedby="describedBy"
            />
          </FormField>
          <FormField
            id="password"
            v-slot="{ describedBy }"
            :label="t('auth.passwordLabel')"
            :errors="fieldErrorsFor('password')"
          >
            <AppInput
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              :invalid="fieldErrorsFor('password').length > 0"
              :aria-describedby="describedBy"
            />
          </FormField>

          <p v-if="generalErrorText" class="text-sm text-danger-700">{{ generalErrorText }}</p>

          <AppButton type="submit" block :disabled="isLoading || !isFormValid">
            {{ isLoading ? t('auth.login.submitting') : t('auth.login.submit') }}
          </AppButton>
        </form>
      </AppCard>

      <p class="mt-6 text-center text-[13px] text-neutral-600">
        {{ t('auth.login.noAccount') }}
        <RouterLink :to="{ name: 'register' }" class="text-accent hover:text-accent-600">{{
          t('auth.login.registerLink')
        }}</RouterLink>
      </p>
    </div>
  </main>
</template>
