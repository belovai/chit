<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import AppSlideOver from '@/components/ui/AppSlideOver.vue'
import ModalFooter from '@/components/ui/ModalFooter.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppCheckbox from '@/components/ui/AppCheckbox.vue'
import { useModalRoute } from '@/composables/useModalRoute'
import { useAuthStore } from '@/stores/auth'
import { merchantService } from '@/services/merchant'
import { ApiError } from '@/types/auth'
import { translateErrorCode } from '@/utils/errors'
import type { Merchant, MerchantLocation, MerchantLocationPayload } from '@/types/merchant'

interface LocationDraft {
  is_online: boolean
  address: string
  latitude: string
  longitude: string
}

function emptyDraft(): LocationDraft {
  return { is_online: false, address: '', latitude: '', longitude: '' }
}

function draftFromLocation(location: MerchantLocation): LocationDraft {
  return {
    is_online: location.is_online,
    address: location.address ?? '',
    latitude: location.latitude === null ? '' : String(location.latitude),
    longitude: location.longitude === null ? '' : String(location.longitude),
  }
}

// A Google Maps vágólapra „46.4106993, 20.3257447" alakban tesz koordinátát.
// Csak pontot fogadunk el tizedesjelnek, mert a vessző itt elválasztó.
const COORDINATE_PAIR = /^\s*(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)\s*$/

function parseCoordinatePair(value: string): { latitude: string; longitude: string } | null {
  const match = COORDINATE_PAIR.exec(value)
  if (!match) {
    return null
  }
  return { latitude: match[1]!, longitude: match[2]! }
}

function draftToPayload(draft: LocationDraft): MerchantLocationPayload {
  return {
    is_online: draft.is_online,
    address: draft.is_online ? null : draft.address || null,
    latitude: draft.is_online || draft.latitude === '' ? null : Number(draft.latitude),
    longitude: draft.is_online || draft.longitude === '' ? null : Number(draft.longitude),
  }
}

export default defineComponent({
  name: 'MerchantFormSlideOver',

  components: {
    AppButton,
    AppSlideOver,
    ModalFooter,
    ConfirmDialog,
    FormField,
    AppInput,
    AppCheckbox,
  },

  beforeRouteLeave(to, from, next) {
    if (!this.isDirty) {
      next()
      return
    }
    this.pendingLeave = next
    this.showUnsavedConfirm = true
  },

  setup() {
    const { t } = useI18n()
    const { close } = useModalRoute('settings-merchants')
    return { t, translateErrorCode, close }
  },

  data() {
    return {
      merchant: null as Merchant | null,
      name: '',
      isLoadingMerchant: false,
      isSavingMerchant: false,
      merchantFieldErrors: {} as Record<string, string[]>,
      merchantGeneralError: null as string | null,

      locations: [] as MerchantLocation[],
      locationDrafts: {} as Record<string, LocationDraft>,
      locationFieldErrors: {} as Record<string, Record<string, string[]>>,
      savingLocationHashId: null as string | null,

      newLocationDraft: emptyDraft(),
      newLocationFieldErrors: {} as Record<string, string[]>,
      isCreatingLocation: false,

      locationPendingDelete: null as MerchantLocation | null,

      isDirty: false,
      showUnsavedConfirm: false,
      pendingLeave: null as null | ((allow?: boolean) => void),
    }
  },

  computed: {
    ...mapState(useAuthStore, ['token']),

    hashId(): string | undefined {
      return this.$route.params.hashId as string | undefined
    },

    isEditMode(): boolean {
      return this.hashId !== undefined
    },

    hasOnlineLocation(): boolean {
      return this.locations.some((location) => location.is_online)
    },
  },

  async mounted() {
    if (this.isEditMode) {
      await this.loadMerchant()
      await this.loadLocations()
    }
  },

  methods: {
    async loadMerchant() {
      this.isLoadingMerchant = true
      try {
        this.merchant = await merchantService.get(this.token as string, this.hashId as string)
        this.name = this.merchant.name
      } finally {
        this.isLoadingMerchant = false
      }
    },

    async loadLocations() {
      this.locations = await merchantService.listLocations(
        this.token as string,
        this.hashId as string,
      )
      this.locationDrafts = Object.fromEntries(
        this.locations.map((location) => [location.hash_id, draftFromLocation(location)]),
      )
    },

    // Koordinátapár beillesztésekor (Google Maps vágólap) magától szétosztjuk
    // a két mező között — bármelyikbe is illesztették be. Gépelésre szándékosan
    // nem fut, mert a félkész `46.41, 2` állapot is illeszkedne a mintára.
    onCoordinatePaste(event: ClipboardEvent, draft: LocationDraft) {
      const pair = parseCoordinatePair(event.clipboardData?.getData('text') ?? '')
      if (!pair) {
        return
      }
      event.preventDefault()
      draft.latitude = pair.latitude
      draft.longitude = pair.longitude
    },

    fieldErrorsFor(field: string): string[] {
      const codes = this.merchantFieldErrors[field] ?? []
      return codes.map((code) => translateErrorCode(this.t, code, field))
    },

    locationFieldErrorsFor(hashId: string, field: string): string[] {
      const codes = this.locationFieldErrors[hashId]?.[field] ?? []
      return codes.map((code) => translateErrorCode(this.t, code, field))
    },

    newLocationFieldErrorsFor(field: string): string[] {
      const codes = this.newLocationFieldErrors[field] ?? []
      return codes.map((code) => translateErrorCode(this.t, code, field))
    },

    requestClose() {
      if (this.isDirty) {
        this.showUnsavedConfirm = true
        return
      }
      this.close()
    },

    confirmDiscard() {
      this.showUnsavedConfirm = false
      this.isDirty = false
      if (this.pendingLeave) {
        const leave = this.pendingLeave
        this.pendingLeave = null
        leave()
        return
      }
      this.close()
    },

    cancelDiscard() {
      this.showUnsavedConfirm = false
      if (this.pendingLeave) {
        const leave = this.pendingLeave
        this.pendingLeave = null
        leave(false)
      }
    },

    async onSubmitMerchant() {
      this.merchantFieldErrors = {}
      this.merchantGeneralError = null
      this.isSavingMerchant = true
      try {
        if (this.isEditMode) {
          this.merchant = await merchantService.update(
            this.token as string,
            this.hashId as string,
            {
              name: this.name,
            },
          )
          this.isDirty = false
        } else {
          this.merchant = await merchantService.create(this.token as string, { name: this.name })
          this.isDirty = false
          await this.$router.push({
            name: 'settings-merchant-edit',
            params: { hashId: this.merchant.hash_id },
          })
        }
      } catch (error) {
        if (error instanceof ApiError) {
          this.merchantFieldErrors = error.errors ?? {}
          this.merchantGeneralError = error.errors ? null : error.message
        } else {
          this.merchantGeneralError = 'network.connection_failed'
        }
      } finally {
        this.isSavingMerchant = false
      }
    },

    async onSaveLocation(location: MerchantLocation) {
      const draft = this.locationDrafts[location.hash_id]!
      this.locationFieldErrors = { ...this.locationFieldErrors, [location.hash_id]: {} }
      this.savingLocationHashId = location.hash_id
      try {
        const updated = await merchantService.updateLocation(
          this.token as string,
          location.hash_id,
          draftToPayload(draft),
        )
        const index = this.locations.findIndex((item) => item.hash_id === location.hash_id)
        this.locations.splice(index, 1, updated)
        this.locationDrafts[location.hash_id] = draftFromLocation(updated)
      } catch (error) {
        if (error instanceof ApiError && error.errors) {
          this.locationFieldErrors = {
            ...this.locationFieldErrors,
            [location.hash_id]: error.errors,
          }
        }
      } finally {
        this.savingLocationHashId = null
      }
    },

    requestDeleteLocation(location: MerchantLocation) {
      this.locationPendingDelete = location
    },

    cancelDeleteLocation() {
      this.locationPendingDelete = null
    },

    async confirmDeleteLocation() {
      const location = this.locationPendingDelete
      if (!location) {
        return
      }
      await merchantService.destroyLocation(this.token as string, location.hash_id)
      this.locations = this.locations.filter((item) => item.hash_id !== location.hash_id)
      delete this.locationDrafts[location.hash_id]
      this.locationPendingDelete = null
    },

    async onCreateLocation() {
      this.newLocationFieldErrors = {}
      this.isCreatingLocation = true
      try {
        const created = await merchantService.createLocation(
          this.token as string,
          this.hashId as string,
          draftToPayload(this.newLocationDraft),
        )
        this.locations.push(created)
        this.locationDrafts[created.hash_id] = draftFromLocation(created)
        this.newLocationDraft = emptyDraft()
      } catch (error) {
        if (error instanceof ApiError && error.errors) {
          this.newLocationFieldErrors = error.errors
        }
      } finally {
        this.isCreatingLocation = false
      }
    },
  },
})
</script>

<template>
  <AppSlideOver
    :title="isEditMode ? t('merchants.editTitle') : t('merchants.createTitle')"
    @close="requestClose"
  >
    <div v-if="isLoadingMerchant" class="flex flex-col gap-3">
      <div class="h-4 w-24 animate-pulse rounded-sm bg-surface"></div>
      <div class="h-9 w-full animate-pulse rounded-md bg-surface"></div>
    </div>

    <div v-else class="flex flex-col gap-6">
      <form id="merchant-form" class="flex flex-col gap-4" @submit.prevent="onSubmitMerchant">
        <FormField
          id="merchant-name"
          v-slot="{ describedBy }"
          :label="t('merchants.nameLabel')"
          :errors="fieldErrorsFor('name')"
        >
          <AppInput
            id="merchant-name"
            :model-value="name"
            :invalid="fieldErrorsFor('name').length > 0"
            :aria-describedby="describedBy"
            @update:model-value="
              (value: string) => {
                name = value
                isDirty = true
              }
            "
          />
        </FormField>
        <p v-if="merchantGeneralError" class="text-sm text-danger-700">
          {{ translateErrorCode(t, merchantGeneralError) }}
        </p>
      </form>

      <section v-if="isEditMode" class="flex flex-col gap-3">
        <h3 class="text-sm font-semibold text-text">{{ t('merchants.locationsTitle') }}</h3>

        <p v-if="locations.length === 0" class="text-[13px] text-neutral-600">
          {{ t('merchants.noLocations') }}
        </p>

        <div
          v-for="location in locations"
          :key="location.hash_id"
          class="flex flex-col gap-3 rounded-lg border border-divider bg-bg p-4"
        >
          <template v-if="location.is_online || !hasOnlineLocation">
            <AppCheckbox
              :id="`location-online-${location.hash_id}`"
              v-model="locationDrafts[location.hash_id]!.is_online"
              :label="t('merchants.isOnlineLabel')"
            />
            <p
              v-for="message in locationFieldErrorsFor(location.hash_id, 'is_online')"
              :key="message"
              class="text-[13px] text-danger-700"
            >
              {{ message }}
            </p>
          </template>

          <FormField
            v-if="!locationDrafts[location.hash_id]!.is_online"
            :id="`location-address-${location.hash_id}`"
            v-slot="{ describedBy }"
            :label="t('merchants.addressLabel')"
            :errors="locationFieldErrorsFor(location.hash_id, 'address')"
          >
            <AppInput
              :id="`location-address-${location.hash_id}`"
              v-model="locationDrafts[location.hash_id]!.address"
              :invalid="locationFieldErrorsFor(location.hash_id, 'address').length > 0"
              :aria-describedby="describedBy"
            />
          </FormField>

          <div v-if="!locationDrafts[location.hash_id]!.is_online" class="flex gap-3">
            <FormField
              :id="`location-lat-${location.hash_id}`"
              v-slot="{ describedBy }"
              :label="t('merchants.latitudeLabel')"
              :errors="locationFieldErrorsFor(location.hash_id, 'latitude')"
              class="min-w-0 flex-1"
            >
              <AppInput
                :id="`location-lat-${location.hash_id}`"
                v-model="locationDrafts[location.hash_id]!.latitude"
                :invalid="locationFieldErrorsFor(location.hash_id, 'latitude').length > 0"
                :aria-describedby="describedBy"
                @paste="
                  (event: ClipboardEvent) =>
                    onCoordinatePaste(event, locationDrafts[location.hash_id]!)
                "
              />
            </FormField>
            <FormField
              :id="`location-lng-${location.hash_id}`"
              v-slot="{ describedBy }"
              :label="t('merchants.longitudeLabel')"
              :errors="locationFieldErrorsFor(location.hash_id, 'longitude')"
              class="min-w-0 flex-1"
            >
              <AppInput
                :id="`location-lng-${location.hash_id}`"
                v-model="locationDrafts[location.hash_id]!.longitude"
                :invalid="locationFieldErrorsFor(location.hash_id, 'longitude').length > 0"
                :aria-describedby="describedBy"
                @paste="
                  (event: ClipboardEvent) =>
                    onCoordinatePaste(event, locationDrafts[location.hash_id]!)
                "
              />
            </FormField>
          </div>

          <div class="flex gap-2">
            <AppButton
              type="button"
              size="sm"
              :disabled="savingLocationHashId === location.hash_id"
              @click="onSaveLocation(location)"
            >
              {{ t('merchants.save') }}
            </AppButton>
            <AppButton
              type="button"
              variant="ghost"
              size="sm"
              @click="requestDeleteLocation(location)"
            >
              {{ t('merchants.deleteLocation') }}
            </AppButton>
          </div>
        </div>

        <div class="flex flex-col gap-3 rounded-lg border border-dashed border-divider p-4">
          <h4 class="text-[13px] font-semibold text-text">{{ t('merchants.addLocationTitle') }}</h4>

          <template v-if="!hasOnlineLocation">
            <AppCheckbox
              id="new-location-online"
              v-model="newLocationDraft.is_online"
              :label="t('merchants.isOnlineLabel')"
            />
            <p
              v-for="message in newLocationFieldErrorsFor('is_online')"
              :key="message"
              class="text-[13px] text-danger-700"
            >
              {{ message }}
            </p>
          </template>

          <FormField
            v-if="!newLocationDraft.is_online"
            id="new-location-address"
            v-slot="{ describedBy }"
            :label="t('merchants.addressLabel')"
            :errors="newLocationFieldErrorsFor('address')"
          >
            <AppInput
              id="new-location-address"
              v-model="newLocationDraft.address"
              :invalid="newLocationFieldErrorsFor('address').length > 0"
              :aria-describedby="describedBy"
            />
          </FormField>

          <div v-if="!newLocationDraft.is_online" class="flex gap-3">
            <FormField
              id="new-location-lat"
              v-slot="{ describedBy }"
              :label="t('merchants.latitudeLabel')"
              :errors="newLocationFieldErrorsFor('latitude')"
              class="min-w-0 flex-1"
            >
              <AppInput
                id="new-location-lat"
                v-model="newLocationDraft.latitude"
                :invalid="newLocationFieldErrorsFor('latitude').length > 0"
                :aria-describedby="describedBy"
                @paste="(event: ClipboardEvent) => onCoordinatePaste(event, newLocationDraft)"
              />
            </FormField>
            <FormField
              id="new-location-lng"
              v-slot="{ describedBy }"
              :label="t('merchants.longitudeLabel')"
              :errors="newLocationFieldErrorsFor('longitude')"
              class="min-w-0 flex-1"
            >
              <AppInput
                id="new-location-lng"
                v-model="newLocationDraft.longitude"
                :invalid="newLocationFieldErrorsFor('longitude').length > 0"
                :aria-describedby="describedBy"
                @paste="(event: ClipboardEvent) => onCoordinatePaste(event, newLocationDraft)"
              />
            </FormField>
          </div>

          <AppButton
            type="button"
            size="sm"
            :disabled="isCreatingLocation"
            @click="onCreateLocation"
          >
            {{ t('merchants.addLocation') }}
          </AppButton>
        </div>
      </section>
    </div>

    <template #footer>
      <ModalFooter>
        <AppButton type="button" variant="ghost" @click="requestClose">
          {{ t('common.cancel') }}
        </AppButton>
        <AppButton
          type="submit"
          form="merchant-form"
          :disabled="isSavingMerchant || name.trim().length === 0"
        >
          {{
            isEditMode
              ? isSavingMerchant
                ? t('merchants.saving')
                : t('merchants.save')
              : isSavingMerchant
                ? t('merchants.creating')
                : t('merchants.create')
          }}
        </AppButton>
      </ModalFooter>
    </template>
  </AppSlideOver>

  <ConfirmDialog
    :open="showUnsavedConfirm"
    :title="t('overlay.unsavedTitle')"
    :message="t('overlay.unsavedMessage')"
    :confirm-label="t('overlay.unsavedConfirm')"
    :cancel-label="t('overlay.unsavedCancel')"
    variant="danger"
    @confirm="confirmDiscard"
    @cancel="cancelDiscard"
  />

  <ConfirmDialog
    :open="locationPendingDelete !== null"
    :message="t('merchants.deleteLocationConfirm')"
    :confirm-label="t('merchants.deleteLocation')"
    variant="danger"
    @confirm="confirmDeleteLocation"
    @cancel="cancelDeleteLocation"
  />
</template>
