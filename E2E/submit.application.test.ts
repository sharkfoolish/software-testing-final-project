import { test, expect } from '@playwright/test';

test('test', async ({ page, context }) => {
    await context.addCookies([
        { name: 'token_name', value: 'token_value', path: '/', domain: 'oauth_domain' }
    ]);
    await page.goto('http://localhost:3000/applicant/history');
    await expect(page.getByRole('cell', { name: 'add' })).toHaveCount(0);
    await page.goto('http://localhost:3000/applicant/apply');
    await page.getByRole('textbox', { name: 'Record Name Record Name' }).fill('test.com');
    await page.getByRole('textbox', { name: 'Record Type Record Type' }).click();
    await page.getByRole('option', { name: 'A', exact: true }).click();
    await page.getByRole('textbox', { name: 'Record Data Record Data' }).fill('127.0.0.1');
    await page.getByRole('button', { name: '送出' }).click();
    await page.goto('http://localhost:3000/applicant/history');
    await expect(page.getByRole('cell', { name: 'add' })).toHaveCount(1);
});