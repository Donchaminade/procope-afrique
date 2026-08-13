<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\View;
use App\Models\Formation;
use App\Models\Inscription;
use App\Models\JobApplication;
use App\Models\JobOffer;

final class DashboardController
{
    public function index(Request $request): void
    {
        $active = Formation::active();
        $activeId = $active ? (int) $active['id'] : null;
        $latest = Inscription::search([], 1, 8);
        $stats = Inscription::countByStatut($activeId);

        // Finances de la formation en cours : encaissé + potentiel (validés + partiels × prix)
        $caActive = $activeId ? Inscription::revenueForFormation($activeId) : 0.0;
        $potentielActive = $active
            ? ($stats['valide'] + $stats['paiement_partiel']) * (float) $active['prix']
            : 0.0;

        // Places restantes (statuts occupant une place, comme l'API publique)
        $placesRestantes = null;
        if ($active && $active['places_max'] !== null) {
            $taken = Formation::countInscriptions(
                $activeId,
                ['preinscrit', 'preuve_recue', 'valide', 'paiement_partiel']
            );
            $placesRestantes = max(0, (int) $active['places_max'] - $taken);
        }

        // Recrutement : offres publiées en cours + suivi des dossiers
        // (requêtes préparées lecture seule dans les modèles)
        $soonestOffer = JobOffer::soonestClosing();

        View::render('dashboard', [
            'title'           => 'Tableau de bord',
            'stats'           => $stats,
            'perDay'          => Inscription::countPerDay(14, $activeId),
            'byGender'        => Inscription::countByGender($activeId),
            'active'          => $active,
            'activeSlots'     => $active ? Formation::slots($activeId) : [],
            'latest'          => $latest['rows'],
            'caTotal'         => Inscription::totalRevenue(),
            'caActive'        => $caActive,
            'resteAEncaisser' => max(0.0, $potentielActive - $caActive),
            'archivedCount'   => Formation::countArchived(),
            'placesRestantes' => $placesRestantes,
            'offresPubliees'  => JobOffer::countPublishedOpen(),
            'dossiersTotal'   => JobApplication::countAll(),
            'soonestOffer'    => $soonestOffer,
            'candidatsRetenus' => JobApplication::countWithStatut('retenue'),
        ]);
    }
}
