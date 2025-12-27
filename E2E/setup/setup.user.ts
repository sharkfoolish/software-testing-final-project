import { test } from '@playwright/test';

test('test', async ({ page, context }) => {
    await context.addCookies([
        { name: 'token_name', value: 'token_value', path: '/', domain: 'oauth_domain' }
    ]);
    await page.goto('http://localhost:3000/applicant/settings');
    await page.getByRole('textbox', { name: '手機號碼 手機號碼' }).fill('0912345678');
    await page.getByRole('button', { name: 'Open' }).click();
    await page.getByRole('option').first().click();
    await page.getByRole('textbox', { name: '辦公室 辦公室' }).fill('test');
    await page.getByRole('textbox', { name: '辦公室分機 辦公室分機' }).fill('12345');
    await page.getByRole('button', { name: '儲存' }).click();
});