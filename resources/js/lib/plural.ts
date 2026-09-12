import type { useTranslations } from '@/hooks/use-translations';

type Translate = ReturnType<typeof useTranslations>['t'];

/**
 * Kiest de enkelvouds- of meervoudsvertaling op basis van `count` en vult
 * `:count` in de vertaling in. Voorkomt onnatuurlijke patronen als
 * "1 Deelnemers".
 */
export function pluralize(
    t: Translate,
    count: number,
    singularKey: string,
    pluralKey: string,
): string {
    return t(count === 1 ? singularKey : pluralKey, { count });
}
