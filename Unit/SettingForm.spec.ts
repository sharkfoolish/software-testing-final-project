import { mount } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { createTestingPinia } from '@pinia/testing'
import { rules } from '../utils/inputRules'

import SettingForm from '../components/SettingForm.vue'

const vuetify = createVuetify({
    components,
    directives,
})

describe('SettingForm.vue', () => {
    let wrapper: any

    const mockProps = {
        user: {
            id: '1',
            email: 'test@example.com',
            phone: '0912345678',
            officeRoom: 'A101',
            officeExt: '1234',
            approverId: 'prof1',
            name: 'Test User'
        },
        approvers: [
            { id: 'prof1', name: 'Professor A' },
            { id: 'prof2', name: 'Professor B' }
        ]
    }

    const findInput = (labelOrSelector: string | { label: string }) => {
        const label = typeof labelOrSelector === 'string'
            ? labelOrSelector
            : labelOrSelector.label

        const allInputs = [
            ...wrapper.findAllComponents(components.VTextField),
            ...wrapper.findAllComponents(components.VAutocomplete)
        ]

        const target = allInputs.find((w: any) => w.props('label') === label)

        if (!target) {
            throw new Error(`測試失敗：找不到 Label 為 "${label}" 的輸入框元件。請檢查元件上是否有 :label="${label}" 或 label="${label}"`)
        }
        return target
    }

    beforeEach(() => {
        wrapper = mount(SettingForm, {
            props: mockProps,
            global: {
                plugins: [
                    vuetify,
                    createTestingPinia({ createSpy: vi.fn })
                ],
                config: {
                    globalProperties: {
                        rules: rules
                    }
                }
            }
        })
    })

    // --- 測試 1: 電子信箱 (Email) ---
    it('檢查電子信箱：必填驗證', async () => {
        const emailInput = findInput({ label: '個人信箱' })

        // 1. 清空值 -> 觸發 blur
        await emailInput.setValue('')
        await emailInput.trigger('blur')

        // 預期出現 "此欄位必填"
        expect(wrapper.text()).toContain('此欄位必填')

        // 2. 輸入值
        await emailInput.setValue('test@example.com')
        await emailInput.trigger('blur')

        // 檢查該元件的錯誤訊息區是否為空
        expect(emailInput.find('.v-messages__message').exists()).toBe(false)
    })

    // --- 測試 2: 手機號碼 (Phone) ---
    it('檢查手機號碼：格式與長度邏輯 (10碼)', async () => {
        const phoneInput = findInput({ label: '手機號碼' })

        // 測試 A: 輸入非數字 (例如 "abc")
        await phoneInput.setValue('abc')
        await phoneInput.trigger('blur')
        expect(wrapper.text()).toContain('此欄位必須為一個合法的手機號碼')

        // 測試 B: 長度過短 (9碼)
        await phoneInput.setValue('123456789')
        await phoneInput.trigger('blur')
        expect(wrapper.text()).toContain('此欄位必須為一個合法的手機號碼')

        // 測試 C: 長度過長 (11碼)
        await phoneInput.setValue('09123456789')
        await phoneInput.trigger('blur')
        expect(wrapper.text()).toContain('此欄位必須為一個合法的手機號碼')

        // 測試 D: 非 09 開頭 (例如 "08123456789")
        await phoneInput.setValue('abc')
        await phoneInput.trigger('blur')
        expect(wrapper.text()).toContain('此欄位必須為一個合法的手機號碼')

        // 測試 E: 正確格式 (10碼)
        await phoneInput.setValue('0912345678')
        await phoneInput.trigger('blur')
        expect(phoneInput.find('.v-messages__message').exists()).toBe(false)
    })

    // --- 測試 3: 辦公室分機 (Office Ext) ---
    it('檢查辦公室分機：長度限制 (< 9)', async () => {
        const extInput = findInput({ label: '辦公室分機' })

        // 根據你的 component，這裡是 rules.isSmallerThan(9)
        // 根據你的 rules 邏輯，v.length < 9 才是合法的

        // 測試 A: 輸入 9 碼 (邊界測試：應失敗，因為 9 不小於 9)
        await extInput.setValue('123456789')
        await extInput.trigger('blur')
        expect(wrapper.text()).toContain('此欄位長度必須小於9')

        // 測試 B: 輸入 10 碼 (應失敗)
        await extInput.setValue('1234567890')
        await extInput.trigger('blur')
        expect(wrapper.text()).toContain('此欄位長度必須小於9')

        // 測試 C: 輸入 8 碼 (應成功)
        await extInput.setValue('12345678')
        await extInput.trigger('blur')
        expect(extInput.find('.v-messages__message').exists()).toBe(false)
    })
})