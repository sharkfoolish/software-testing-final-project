<script setup lang="ts">
import {
  type Application,
  ApplicationActionEnum,
  RecordTypeEnum,
} from '@www/dnsmgmt'
import {VForm} from 'vuetify/lib/components/index.mjs'
import {useSimpleForm} from '~/composables/useSimpleForm'
import {useSnackbarStore} from '~/store/snackbar'

interface Props {
  application?: Application
  showExtended?: boolean
}

const props = defineProps<Props>()

const emits = defineEmits<{
  (e: 'submit', data: Application, onComplete: (success: boolean) => void): void
}>()
const app = {
  action: ApplicationActionEnum.ADD,
  officeRoom: '',
  officeExt: '',
  recordName: '',
  recordType: RecordTypeEnum.A,
  recordData: '',
  approverId: undefined,
} as Application
const {form} = useSimpleForm<Application>(props.application ?? app)

const formRef = ref<VForm>()

const {toggle: toggleSnackbar, setMessage} = useSnackbarStore()
const handleSubmit = () => {
  form.loading = true
  emits('submit', form.data!, (success: boolean) => {
    form.loading = false

    const message = success ? '申請成功送出' : '申請送出失敗'
    if (success) {
      formRef.value?.reset()
    }
    setMessage(message)
    toggleSnackbar(true)
  })
}

const validationMap: Record<string, (v: any) => boolean | string> = {
  [RecordTypeEnum.A]: isIPv4,
  [RecordTypeEnum.CNAME]: isDomain,
  [RecordTypeEnum.AAAA]: isIPv6,
  [RecordTypeEnum.PTR]: isDomain
};

const recordNameRules = computed(() => {
  const rule: ((v: any) => (boolean | string))[] = [rules.required];

  const currentType = form.data?.recordType;

  if (currentType == RecordTypeEnum.PTR) {
    rule.push(isIP);
  } else {
    rule.push(isDomain);
  }

  return rule;
});

const recordDataRules = computed(() => {
  const rule: ((v: any) => (boolean | string))[] = [rules.required];

  const currentType = form.data?.recordType;

  if (currentType && validationMap[currentType]) {
    rule.push(validationMap[currentType]);
  }

  return rule;
});
</script>

<template>
  <v-form
      ref="formRef"
      v-model="form.valid"
      :disabled="form.loading"
      @submit.prevent="handleSubmit"
  >
    <v-row>
      <v-text-field
          v-model="form.data!.recordName"
          clearable
          label="Record Name"
          :rules="recordNameRules"
      />
    </v-row>
    <v-row>
      <v-autocomplete
          v-model="form.data!.recordType"
          :items="Object.values(RecordTypeEnum)"
          label="Record Type"
      />
    </v-row>
    <v-row>
      <v-text-field
          v-model="form.data!.recordData"
          :rules="recordDataRules"
          label="Record Data"
      />
    </v-row>
    <v-row>
      <v-text-field v-model="form.data!.remark" label="備註"/>
    </v-row>

    <ApplicaitonFormExtendedOptions
        v-if="showExtended"
        v-model:form-data="form.data!"
    />

    <v-row justify="end" class="mb-2">
      <v-btn
          :disabled="!form.valid"
          :loading="form.loading"
          type="submit"
          variant="outlined"
          color="primary"
          prepend-icon="mdi-send"
      >
        送出
      </v-btn>
    </v-row>
  </v-form>
</template>
