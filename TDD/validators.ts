import {z} from 'zod';

const domainSchema = z.string().regex(
    /^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}$/
);
const IPv4Schema = z.ipv4();
const IPv6Schema = z.ipv6();
const IPSchema = IPv4Schema.or(IPv6Schema);

export const isDomain = (value: any): (boolean | string) => {
    if (!value) return true;
    const result = domainSchema.safeParse(value);
    return result.success ? true : "此欄位必須填入域名";
};

export const isIPv4 = (value: any): (boolean | string) => {
    if (!value) return true;
    const result = IPv4Schema.safeParse(value);
    return result.success ? true : "此欄位必須填入 IPv4 地址";
}

export const isIPv6 = (value: any): (boolean | string) => {
    if (!value) return true;
    const result = IPv6Schema.safeParse(value);
    return result.success ? true : "此欄位必須填入 IPv6 地址";
}

export const isIP = (value: any): (boolean | string) => {
    if (!value) return true;
    const result = IPSchema.safeParse(value);
    return result.success ? true : "此欄位必須填入 IP 地址";
}

