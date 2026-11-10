import { useState } from 'react';

/**
 * Houdt de laatst geleverde waarde van een uitgestelde (`Inertia::defer`)
 * prop vast, zodat wat ervan afhangt gemonteerd blijft zodra de prop één
 * keer geladen is.
 *
 * Een uitgestelde prop zit alleen in het antwoord op het vervolgverzoek dat
 * Inertia er zelf voor doet, niet in een gewoon pagina-antwoord. Na elk
 * formulier dat terugleidt naar dezelfde pagina is de prop dus even
 * `undefined`, ook -- en dat is het eigenlijke probleem -- bij een antwoord
 * met validatiefouten. Wie direct op de prop (of op `<Deferred>`) rendert,
 * valt dan terug op het skelet en haalt de hele tab uit de boom, inclusief
 * een open dialoog en haar veldfouten. De beheerder ziet dan nooit wélk veld
 * het probleem was en verliest bovendien de focus naar `<body>`.
 *
 * Met deze hook verschijnt het skelet alleen bij de allereerste load; zodra
 * Inertia de prop opnieuw levert, vervangt de verse waarde de oude.
 *
 * De vorige waarde wordt tijdens het renderen bijgewerkt in plaats van in een
 * effect (React's patroon voor informatie uit een vorige render): zo staat
 * een verse waarde meteen in beeld, zonder eerst nog één render met de oude.
 */
export function useRetainedProp<T>(value: T | undefined): T | undefined {
    const [retained, setRetained] = useState(value);

    if (value !== undefined && value !== retained) {
        setRetained(value);
    }

    return value ?? retained;
}
