<script lang="ts">
import { defineComponent } from 'vue'
import { mapActions, mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppSection from '@/components/ui/AppSection.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppCardRow from '@/components/ui/AppCardRow.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import DangerZone from '@/components/ui/DangerZone.vue'
import { useAuthStore } from '@/stores/auth'
import { accountService } from '@/services/account'
import { ApiError } from '@/types/auth'
import type { UpdateAccountPayload } from '@/types/account'
import { isValidEmail } from '@/utils/validators'
import { translateErrorCode } from '@/utils/errors'

type SavedBlock = 'general' | 'password'

export default defineComponent({
  name: 'SettingsAccountView',

  components: {
    AppSection,
    AppCard,
    AppCardRow,
    AppButton,
    AppInput,
    DangerZone,
  },

  setup() {
    const { t } = useI18n()
    return { t }
  },

  data() {
    return {
      general: {
        name: '',
        email: '',
      },
      password: {
        currentPassword: '',
        newPassword: '',
      },
      savingBlock: null as SavedBlock | null,
      savedBlock: null as SavedBlock | null,
      savedTimeout: null as ReturnType<typeof setTimeout> | null,
      fieldErrors: {} as Record<string, string[]>,
      generalError: null as string | null,
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token', 'user']),

    generalErrorText(): string {
      return this.generalError ? translateErrorCode(this.t, this.generalError) : ''
    },

    isGeneralDirty(): boolean {
      return (
        this.general.name !== (this.user?.name ?? '') ||
        this.general.email !== (this.user?.email ?? '')
      )
    },

    isGeneralValid(): boolean {
      return this.general.name.trim().length > 0 && isValidEmail(this.general.email)
    },

    isPasswordDirty(): boolean {
      return this.password.currentPassword.length > 0 || this.password.newPassword.length > 0
    },

    isPasswordValid(): boolean {
      return (
        this.password.currentPassword.length > 0 &&
        this.password.newPassword.length >= 6 &&
        this.password.newPassword !== this.password.currentPassword
      )
    },

    // A művelet-sáv csak akkor jelenik meg, ha van mit menteni — mentés után
    // még látszik, amíg a visszajelzés el nem tűnik.
    showGeneralActions(): boolean {
      return this.isGeneralDirty || this.savedBlock === 'general'
    },

    showPasswordActions(): boolean {
      return this.isPasswordDirty || this.savedBlock === 'password'
    },
  },

  // A localStorage-ban tárolt user elavulhat (másik eszközön módosítás),
  // ezért a szervertől kérjük le a friss adatot, és arról töltjük a formot.
  async mounted() {
    this.resetGeneral()

    try {
      this.setUser(await accountService.get(this.token as string))
      this.resetGeneral()
    } catch (error) {
      this.handleError(error)
    }
  },

  unmounted() {
    this.clearSaved()
  },

  methods: {
    ...mapActions(useAuthStore, ['setUser']),

    // A siker-visszajelzés tartja nyitva a művelet-sávot; időzítve tűnik el,
    // különben mentés után is ott maradna a sáv.
    markSaved(block: SavedBlock) {
      this.clearSaved()
      this.savedBlock = block
      this.savedTimeout = setTimeout(() => {
        this.savedBlock = null
        this.savedTimeout = null
      }, 3000)
    },

    clearSaved() {
      if (this.savedTimeout !== null) {
        clearTimeout(this.savedTimeout)
        this.savedTimeout = null
      }
      this.savedBlock = null
    },

    resetErrors() {
      this.fieldErrors = {}
      this.generalError = null
    },

    fieldErrorsFor(field: string): string[] {
      return (this.fieldErrors[field] ?? []).map((code) => translateErrorCode(this.t, code, field))
    },

    resetGeneral() {
      this.general.name = this.user?.name ?? ''
      this.general.email = this.user?.email ?? ''
      this.clearSaved()
      this.resetErrors()
    },

    resetPassword() {
      this.password.currentPassword = ''
      this.password.newPassword = ''
      this.clearSaved()
      this.resetErrors()
    },

    async saveGeneral() {
      if (!this.isGeneralDirty || !this.isGeneralValid) return

      // Csak a ténylegesen változott mezőt küldjük, hogy a változatlan email ne
      // fusson feleslegesen unique ellenőrzésre.
      const payload: UpdateAccountPayload = {}
      if (this.general.name !== this.user?.name) payload.name = this.general.name.trim()
      if (this.general.email !== this.user?.email) payload.email = this.general.email.trim()

      await this.submit('general', async () => {
        this.setUser(await accountService.update(this.token as string, payload))
        this.resetGeneral()
      })
    },

    async savePassword() {
      if (!this.isPasswordValid) return

      await this.submit('password', async () => {
        await accountService.changePassword(this.token as string, {
          current_password: this.password.currentPassword,
          password: this.password.newPassword,
        })
        this.password.currentPassword = ''
        this.password.newPassword = ''
      })
    },

    async submit(block: SavedBlock, request: () => Promise<void>) {
      this.resetErrors()
      this.clearSaved()
      this.savingBlock = block
      try {
        await request()
        this.markSaved(block)
      } catch (error) {
        this.handleError(error)
      } finally {
        this.savingBlock = null
      }
    },

    handleError(error: unknown) {
      if (error instanceof ApiError) {
        this.fieldErrors = error.errors ?? {}
        this.generalError = error.errors ? null : error.message
        return
      }
      this.generalError = 'network.connection_failed'
    },
  },
})
</script>

<template>
  <div class="flex flex-col gap-8">
    <AppSection :title="t('profile.general')" :description="t('profile.generalDescription')">
      <AppCard :padded="false">
        <form @submit.prevent="saveGeneral">
          <div class="divide-y divide-divider">
            <AppCardRow :label="t('profile.nameLabel')" :description="t('profile.nameHint')">
              <div class="flex flex-col gap-1.5 sm:w-72 sm:text-left">
                <AppInput
                  id="account-name"
                  v-model="general.name"
                  autocomplete="name"
                  :invalid="fieldErrorsFor('name').length > 0"
                  :aria-label="t('profile.nameLabel')"
                  :aria-describedby="
                    fieldErrorsFor('name').length > 0 ? 'account-name-error' : undefined
                  "
                />
                <p
                  v-if="fieldErrorsFor('name').length > 0"
                  id="account-name-error"
                  class="text-[13px] text-danger-700"
                >
                  {{ fieldErrorsFor('name').join(' ') }}
                </p>
              </div>
            </AppCardRow>

            <AppCardRow :label="t('profile.emailLabel')" :description="t('profile.emailHint')">
              <div class="flex flex-col gap-1.5 sm:w-72 sm:text-left">
                <AppInput
                  id="account-email"
                  v-model="general.email"
                  type="email"
                  autocomplete="username"
                  :invalid="fieldErrorsFor('email').length > 0"
                  :aria-label="t('profile.emailLabel')"
                  :aria-describedby="
                    fieldErrorsFor('email').length > 0 ? 'account-email-error' : undefined
                  "
                />
                <p
                  v-if="fieldErrorsFor('email').length > 0"
                  id="account-email-error"
                  class="text-[13px] text-danger-700"
                >
                  {{ fieldErrorsFor('email').join(' ') }}
                </p>
              </div>
            </AppCardRow>

            <AppCardRow :label="t('profile.generalAvatarPlaceholder')" />
          </div>

          <!-- Összecsukott állapotban `inert`, különben a nem látszó gombokra is
               rá lehetne fókuszálni tabbal. -->
          <div
            class="grid transition-all duration-150 ease-out"
            :class="
              showGeneralActions ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'
            "
            :inert="!showGeneralActions"
          >
            <div class="overflow-hidden">
              <div
                class="flex items-center justify-end gap-2 border-t border-divider bg-surface px-5 py-3"
              >
                <p v-if="savedBlock === 'general'" class="mr-auto text-[13px] text-neutral-600">
                  {{ t('profile.generalSaved') }}
                </p>
                <AppButton
                  variant="ghost"
                  size="sm"
                  :disabled="!isGeneralDirty || savingBlock !== null"
                  @click="resetGeneral"
                >
                  {{ t('profile.reset') }}
                </AppButton>
                <AppButton
                  type="submit"
                  size="sm"
                  :disabled="!isGeneralDirty || !isGeneralValid || savingBlock !== null"
                >
                  {{ savingBlock === 'general' ? t('profile.saving') : t('profile.save') }}
                </AppButton>
              </div>
            </div>
          </div>
        </form>
      </AppCard>
    </AppSection>

    <AppSection :title="t('profile.security')" :description="t('profile.securityDescription')">
      <AppCard :padded="false">
        <form @submit.prevent="savePassword">
          <div class="divide-y divide-divider">
            <AppCardRow
              :label="t('profile.currentPasswordLabel')"
              :description="t('profile.currentPasswordHint')"
            >
              <div class="flex flex-col gap-1.5 sm:w-72 sm:text-left">
                <AppInput
                  id="account-current-password"
                  v-model="password.currentPassword"
                  type="password"
                  autocomplete="current-password"
                  :invalid="fieldErrorsFor('current_password').length > 0"
                  :aria-label="t('profile.currentPasswordLabel')"
                  :aria-describedby="
                    fieldErrorsFor('current_password').length > 0
                      ? 'account-current-password-error'
                      : undefined
                  "
                />
                <p
                  v-if="fieldErrorsFor('current_password').length > 0"
                  id="account-current-password-error"
                  class="text-[13px] text-danger-700"
                >
                  {{ fieldErrorsFor('current_password').join(' ') }}
                </p>
              </div>
            </AppCardRow>

            <AppCardRow
              :label="t('profile.newPasswordLabel')"
              :description="t('profile.passwordHint')"
            >
              <div class="flex flex-col gap-1.5 sm:w-72 sm:text-left">
                <AppInput
                  id="account-new-password"
                  v-model="password.newPassword"
                  type="password"
                  autocomplete="new-password"
                  :invalid="fieldErrorsFor('password').length > 0"
                  :aria-label="t('profile.newPasswordLabel')"
                  :aria-describedby="
                    fieldErrorsFor('password').length > 0 ? 'account-new-password-error' : undefined
                  "
                />
                <p
                  v-if="fieldErrorsFor('password').length > 0"
                  id="account-new-password-error"
                  class="text-[13px] text-danger-700"
                >
                  {{ fieldErrorsFor('password').join(' ') }}
                </p>
              </div>
            </AppCardRow>

            <AppCardRow :label="t('profile.securityTwoFactorPlaceholder')" />
          </div>

          <!-- Összecsukott állapotban `inert`, különben a nem látszó gombokra is
               rá lehetne fókuszálni tabbal. -->
          <div
            class="grid transition-all duration-150 ease-out"
            :class="
              showPasswordActions ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'
            "
            :inert="!showPasswordActions"
          >
            <div class="overflow-hidden">
              <div
                class="flex items-center justify-end gap-2 border-t border-divider bg-surface px-5 py-3"
              >
                <p v-if="savedBlock === 'password'" class="mr-auto text-[13px] text-neutral-600">
                  {{ t('profile.passwordSaved') }}
                </p>
                <AppButton
                  variant="ghost"
                  size="sm"
                  :disabled="!isPasswordDirty || savingBlock !== null"
                  @click="resetPassword"
                >
                  {{ t('profile.reset') }}
                </AppButton>
                <AppButton
                  type="submit"
                  size="sm"
                  :disabled="!isPasswordValid || savingBlock !== null"
                >
                  {{ savingBlock === 'password' ? t('profile.saving') : t('profile.save') }}
                </AppButton>
              </div>
            </div>
          </div>
        </form>
      </AppCard>
    </AppSection>

    <p v-if="generalErrorText" class="text-sm text-danger-700">{{ generalErrorText }}</p>

    <DangerZone :title="t('profile.danger')">
      <AppCardRow :label="t('profile.dangerDeletePlaceholder')" />
    </DangerZone>
  </div>
</template>
