<?php

namespace App\Enums;

/**
 * Het competitietype bepaalt volgens welk algoritme de wedstrijdenlijst wordt
 * gegenereerd (aantal ronden, indeling in poules, enkel/dubbel, etc.). Elk
 * type krijgt zijn eigen generator; dit fundament kent er nog maar één.
 */
enum CompetitionType: string
{
    /**
     * Enkelvoudige round-robin voor enkels/tafeltennis: iedere deelnemer
     * speelt precies één keer tegen elke andere deelnemer.
     */
    case TheoSchilthuizenBokaal = 'theo-schilthuizen-bokaal';
}
