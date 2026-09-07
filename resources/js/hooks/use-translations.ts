import { usePage } from '@inertiajs/react';

/**
 * UI translations shared by HandleInertiaRequests, keyed by source string.
 * Falls back to the key itself when no translation exists for the locale.
 */
export function useTranslations() {
    const { translations } = usePage().props;

    function t(
        key: string,
        replacements: Record<string, string | number> = {},
    ): string {
        let translation = translations[key] ?? key;

        for (const [search, value] of Object.entries(replacements)) {
            translation = translation.replaceAll(`:${search}`, String(value));
        }

        return translation;
    }

    return { t };
}
