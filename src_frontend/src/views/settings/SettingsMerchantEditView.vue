<script lang="ts">
import { defineComponent } from 'vue'
import { mapState } from 'pinia'
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
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

function draftToPayload(draft: LocationDraft): MerchantLocationPayload {
  return {
    is_online: draft.is_online,
    address: draft.is_online ? null : draft.address || null,
    latitude: draft.is_online || draft.latitude === '' ? null : Number(draft.latitude),
    longitude: draft.is_online || draft.longitude === '' ? null : Number(draft.longitude),
  }
}

export default defineComponent({
  name: 'SettingsMerchantEditView',

  components: {
    AppButton,
    ConfirmDialog,
    FormField,
  },

  setup() {
    const { t } = useI18n()
    return { t, translateErrorCode }
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
        } else {
          this.merchant = await merchantService.create(this.token as string, { name: this.name })
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
  <div class="flex flex-col gap-6">
    <RouterLink
      :to="{ name: 'settings-merchants' }"
      class="text-sm text-accent hover:text-accent-600"
    >
      {{ t('merchants.backToList') }}
    </RouterLink>

    <form
      class="flex flex-col gap-4 border border-divider bg-surface p-6"
      @submit.prevent="onSubmitMerchant"
    >
      <FormField
        id="merchant-name"
        v-model="name"
        :label="t('merchants.nameLabel')"
        :errors="fieldErrorsFor('name')"
      />
      <p v-if="merchantGeneralError" class="text-sm text-danger-700">
        {{ translateErrorCode(t, merchantGeneralError) }}
      </p>
      <AppButton type="submit" :disabled="isSavingMerchant || name.trim().length === 0">
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
    </form>

    <div v-if="isEditMode" class="flex flex-col gap-4 border border-divider bg-surface p-6">
      <h2 class="text-lg">{{ t('merchants.locationsTitle') }}</h2>

      <p v-if="locations.length === 0" class="text-sm text-neutral-600">
        {{ t('merchants.noLocations') }}
      </p>

      <div
        v-for="location in locations"
        :key="location.hash_id"
        class="flex flex-col gap-3 border border-divider p-4"
      >
        <template v-if="location.is_online || !hasOnlineLocation">
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" v-model="locationDrafts[location.hash_id]!.is_online" />
            {{ t('merchants.isOnlineLabel') }}
          </label>
          <p
            v-for="message in locationFieldErrorsFor(location.hash_id, 'is_online')"
            :key="message"
            class="text-xs text-danger-700"
          >
            {{ message }}
          </p>
        </template>
        <FormField
          v-if="!locationDrafts[location.hash_id]!.is_online"
          :id="`location-address-${location.hash_id}`"
          v-model="locationDrafts[location.hash_id]!.address"
          :label="t('merchants.addressLabel')"
          :errors="locationFieldErrorsFor(location.hash_id, 'address')"
        />
        <div v-if="!locationDrafts[location.hash_id]!.is_online" class="flex gap-3">
          <FormField
            :id="`location-lat-${location.hash_id}`"
            v-model="locationDrafts[location.hash_id]!.latitude"
            :label="t('merchants.latitudeLabel')"
            :errors="locationFieldErrorsFor(location.hash_id, 'latitude')"
            class="min-w-0 flex-1"
          />
          <FormField
            :id="`location-lng-${location.hash_id}`"
            v-model="locationDrafts[location.hash_id]!.longitude"
            :label="t('merchants.longitudeLabel')"
            :errors="locationFieldErrorsFor(location.hash_id, 'longitude')"
            class="min-w-0 flex-1"
          />
        </div>
        <div class="flex gap-3">
          <AppButton
            type="button"
            :disabled="savingLocationHashId === location.hash_id"
            @click="onSaveLocation(location)"
          >
            {{ t('merchants.save') }}
          </AppButton>
          <AppButton type="button" variant="ghost" @click="requestDeleteLocation(location)">
            {{ t('merchants.deleteLocation') }}
          </AppButton>
        </div>
      </div>

      <div class="flex flex-col gap-3 border border-dashed border-divider p-4">
        <h3 class="text-sm font-semibold">{{ t('merchants.addLocationTitle') }}</h3>
        <template v-if="!hasOnlineLocation">
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" v-model="newLocationDraft.is_online" />
            {{ t('merchants.isOnlineLabel') }}
          </label>
          <p
            v-for="message in newLocationFieldErrorsFor('is_online')"
            :key="message"
            class="text-xs text-danger-700"
          >
            {{ message }}
          </p>
        </template>
        <FormField
          v-if="!newLocationDraft.is_online"
          id="new-location-address"
          v-model="newLocationDraft.address"
          :label="t('merchants.addressLabel')"
          :errors="newLocationFieldErrorsFor('address')"
        />
        <div v-if="!newLocationDraft.is_online" class="flex gap-3">
          <FormField
            id="new-location-lat"
            v-model="newLocationDraft.latitude"
            :label="t('merchants.latitudeLabel')"
            :errors="newLocationFieldErrorsFor('latitude')"
            class="min-w-0 flex-1"
          />
          <FormField
            id="new-location-lng"
            v-model="newLocationDraft.longitude"
            :label="t('merchants.longitudeLabel')"
            :errors="newLocationFieldErrorsFor('longitude')"
            class="min-w-0 flex-1"
          />
        </div>
        <AppButton type="button" :disabled="isCreatingLocation" @click="onCreateLocation">
          {{ t('merchants.addLocation') }}
        </AppButton>
      </div>
    </div>

    <ConfirmDialog
      :open="locationPendingDelete !== null"
      :message="t('merchants.deleteLocationConfirm')"
      :confirm-label="t('merchants.deleteLocation')"
      variant="danger"
      @confirm="confirmDeleteLocation"
      @cancel="cancelDeleteLocation"
    />
  </div>
</template>
