import {describe, expect, it} from 'vitest'
import {mountSuspended} from '@nuxt/test-utils/runtime'
import ApplicationForm from '~/components/ApplicationForm.vue'
import * as components from 'vuetify/components'

async function setRecordType(wrapper: any, type: string) {
    const recordTypeField = wrapper.findAllComponents(components.VAutocomplete)
        .find((c: { props: (arg0: string) => string; }) => c.props('label') === 'Record Type');

    await recordTypeField?.setValue(type);

    await wrapper.vm.$nextTick();
}

describe('ApplicationForm Component', () => {
    const bindingCases = [
        '192.168.1.1',
        'example.com',
        'some-text-data',
        '::1'
    ]

    it.each(bindingCases)('Record Data 欄位應該能正確更新資料為: %s', async (inputValue) => {
        const wrapper = await mountSuspended(ApplicationForm)

        const recordDataField = wrapper.findAllComponents(components.VTextField)
            .find(c => c.props('label') === 'Record Data')

        expect(recordDataField).toBeDefined()

        await recordDataField?.setValue(inputValue)

        expect((wrapper.vm as any).form.data.recordData).toBe(inputValue)
    })

    const fieldRequiredValueTestCases = [
        'Record Data',
        'Record Name'
    ]

    it.each(fieldRequiredValueTestCases)('當 %s 欄位為空值時，應顯示錯誤訊息', async (fieldLabel) => {
        const wrapper = await mountSuspended(ApplicationForm)

        const fieldComponent = wrapper.findAllComponents(components.VTextField)
            .find(c => c.props('label') === fieldLabel)

        expect(fieldComponent).toBeDefined()

        await fieldComponent?.setValue('temp')
        await wrapper.vm.$nextTick()

        await fieldComponent?.setValue('')
        await fieldComponent?.trigger('blur')

        await new Promise(resolve => setTimeout(resolve, 300))
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain('此欄位必填')
    })

    const fieldNotRequiredValueTestCases = [
        '備註'
    ]

    it.each(fieldNotRequiredValueTestCases)('當 %s 欄位為空值時，不應顯示錯誤訊息', async (fieldLabel) => {
        const wrapper = await mountSuspended(ApplicationForm)

        const fieldComponent = wrapper.findAllComponents(components.VTextField)
            .find(c => c.props('label') === fieldLabel)

        expect(fieldComponent).toBeDefined()

        await fieldComponent?.setValue('temp')
        await wrapper.vm.$nextTick()

        await fieldComponent?.setValue('')
        await fieldComponent?.trigger('blur')

        await new Promise(resolve => setTimeout(resolve, 300))
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).not.toContain('此欄位必填')
    })

    const recordNameValidationTestCases = [
        {
            type: 'A',
            validValue: 'example.com',
            invalidValue: 'invalid_host!',
            expectedError: '此欄位必須填入域名'
        },
        {
            type: 'AAAA',
            validValue: 'example.com',
            invalidValue: '8.8.8.8',
            expectedError: '此欄位必須填入域名'
        },
        {
            type: 'PTR',
            validValue: '192.168.1.1',
            invalidValue: 'invalid.domain.com',
            expectedError: '此欄位必須填入 IP 地址'
        }
    ]

    it.each(recordNameValidationTestCases)('當 Record Type 欄位為 $type 時，且 Record Name 欄位為無效值 ($invalidValue) 時，應顯示錯誤訊息', async ({
                                                                                                                                                      type,
                                                                                                                                                      invalidValue,
                                                                                                                                                      expectedError
                                                                                                                                                  }) => {
        const wrapper = await mountSuspended(ApplicationForm)

        const recordNameField = wrapper.findAllComponents(components.VTextField)
            .find(c => c.props('label') === 'Record Name')

        expect(recordNameField).toBeDefined()

        await setRecordType(wrapper, type)

        await recordNameField?.setValue(invalidValue)
        await recordNameField?.trigger('blur')

        await new Promise(resolve => setTimeout(resolve, 300))
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain(expectedError)
    })

    it.each(recordNameValidationTestCases)('當 Record Type 欄位為 $type 且 Record Name 欄位為有效值 ($validValue) 時，不應顯示錯誤訊息', async ({
                                                                                                                                                   type,
                                                                                                                                                   validValue,
                                                                                                                                                   expectedError
                                                                                                                                               }) => {
        const wrapper = await mountSuspended(ApplicationForm)

        const recordNameField = wrapper.findAllComponents(components.VTextField)
            .find(c => c.props('label') === 'Record Name')

        expect(recordNameField).toBeDefined()

        await setRecordType(wrapper, type)

        await recordNameField?.setValue(validValue)
        await recordNameField?.trigger('blur')

        await new Promise(resolve => setTimeout(resolve, 300))
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).not.toContain(expectedError)
    })

    const recordDataValidationTestCases = [
        {
            type: 'A',
            validValue: '192.168.1.1',
            invalidValue: 'not-an-ipv4',
            expectedError: '此欄位必須填入 IPv4 地址'
        },
        {
            type: 'AAAA',
            validValue: '::1',
            invalidValue: 'not-an-ipv6',
            expectedError: '此欄位必須填入 IPv6 地址'
        },
        {
            type: 'CNAME',
            validValue: 'example.com',
            invalidValue: 'invalid_domain',
            expectedError: '此欄位必須填入域名'
        },
        {
            type: 'PTR',
            validValue: 'example.com',
            invalidValue: '192.168.1.1',
            expectedError: '此欄位必須填入域名'
        }
    ]

    it.each(recordDataValidationTestCases)('當 Record Type 欄位為 $type 且 Record Name 欄位為無效值 ($invalidValue) 時，應顯示錯誤訊息', async ({
                                                                                                                                                   type,
                                                                                                                                                   invalidValue,
                                                                                                                                                   expectedError
                                                                                                                                               }) => {
        const wrapper = await mountSuspended(ApplicationForm)

        const recordTypeField = wrapper.findAllComponents(components.VAutocomplete)
            .find(c => c.props('label') === 'Record Type')
        const recordDataField = wrapper.findAllComponents(components.VTextField)
            .find(c => c.props('label') === 'Record Data')

        expect(recordTypeField).toBeDefined()
        expect(recordDataField).toBeDefined()

        await recordTypeField?.setValue(type)

        await recordDataField?.setValue(invalidValue)
        await recordDataField?.trigger('blur')

        await new Promise(resolve => setTimeout(resolve, 300))
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain(expectedError)
    })

    it.each(recordDataValidationTestCases)('當 Record Type 欄位為 $type 且 Record Name 欄位為有效值 ($validValue) 時，不應顯示錯誤訊息', async ({
                                                                                                                                                   type,
                                                                                                                                                   validValue,
                                                                                                                                                   expectedError
                                                                                                                                               }) => {
        const wrapper = await mountSuspended(ApplicationForm)

        const recordTypeField = wrapper.findAllComponents(components.VAutocomplete)
            .find(c => c.props('label') === 'Record Type')
        const recordDataField = wrapper.findAllComponents(components.VTextField)
            .find(c => c.props('label') === 'Record Data')

        await recordTypeField?.setValue(type)

        await recordDataField?.setValue(validValue)
        await recordDataField?.trigger('blur')

        expect((wrapper.vm as any).form.data.recordData).toBe(validValue)

        await new Promise(resolve => setTimeout(resolve, 300))
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).not.toContain(expectedError)
    })
})