/**
 * Achtergrond op de sticky eerste kolom, zodat de horizontaal scrollende
 * inhoud er niet onderdoor schuift (ook niet in dark mode). Effen
 * `bg-background` in plaats van een halftransparante kleur, om dezelfde reden.
 *
 * Gedeeld door de brede tabellen met een vastgezette eerste kolom
 * (`availability-matrix.tsx` en `schedule-grid.tsx`), zodat ze niet uit elkaar
 * kunnen lopen.
 */
export const STICKY_COLUMN_CLASSES =
    'sticky left-0 z-10 bg-background group-hover:bg-muted';
