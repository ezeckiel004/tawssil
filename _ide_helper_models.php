<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property string $id
 * @property string $message
 * @property string $user_id
 * @property int $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ResponseAvis> $responses
 * @property-read int|null $responses_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|Avis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Avis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Avis query()
 * @method static \Illuminate\Database\Eloquent\Builder|Avis whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Avis whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Avis whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Avis whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Avis whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Avis whereUserId($value)
 */
	class Avis extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read \App\Models\Client|null $client
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Livraison> $livraisons
 * @property-read int|null $livraisons_count
 * @method static \Illuminate\Database\Eloquent\Builder|Bordereau newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Bordereau newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Bordereau query()
 */
	class Bordereau extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $user_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Bordereau> $bordereaux
 * @property-read int|null $bordereaux_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DemandeLivraison> $demandeLivraisons
 * @property-read int|null $demande_livraisons_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Livraison> $livraisons
 * @property-read int|null $livraisons_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|Client newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Client newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Client query()
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Client whereUserId($value)
 */
	class Client extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $code
 * @property string|null $description
 * @property string $type
 * @property float $valeur
 * @property float|null $min_commande
 * @property int|null $max_utilisations
 * @property int $utilisations_actuelles
 * @property \Illuminate\Support\Carbon|null $date_debut
 * @property \Illuminate\Support\Carbon|null $date_fin
 * @property string $gestionnaire_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gestionnaire $gestionnaire
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Livreur> $livreurs
 * @property-read int|null $livreurs_count
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo actif()
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo byGestionnaire($gestionnaireId)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo query()
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo valable()
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereDateDebut($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereDateFin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereGestionnaireId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereMaxUtilisations($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereMinCommande($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereUtilisationsActuelles($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CodePromo whereValeur($value)
 */
	class CodePromo extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $colis_type
 * @property string $colis_label
 * @property string|null $colis_photo
 * @property string|null $colis_photo_url
 * @property string|null $colis_description
 * @property float|null $colis_prix
 * @property float $poids
 * @property float $hauteur
 * @property float $largeur
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DemandeLivraison> $demandeLivraisons
 * @property-read int|null $demande_livraisons_count
 * @method static \Illuminate\Database\Eloquent\Builder|Colis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Colis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Colis query()
 * @method static \Illuminate\Database\Eloquent\Builder|Colis whereColisDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Colis whereColisLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Colis whereColisPhoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Colis whereColisPhotoUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Colis whereColisPrix($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Colis whereColisType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Colis whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Colis whereHauteur($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Colis whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Colis whereLargeur($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Colis wherePoids($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Colis whereUpdatedAt($value)
 */
	class Colis extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $message
 * @property \App\Models\Livreur $livreur
 * @property string $livreur_id
 * @property string $livraison_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Livraison $livraison
 * @method static \Illuminate\Database\Eloquent\Builder|Commentaire newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Commentaire newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Commentaire query()
 * @method static \Illuminate\Database\Eloquent\Builder|Commentaire whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Commentaire whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Commentaire whereLivraisonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Commentaire whereLivreur($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Commentaire whereLivreurId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Commentaire whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Commentaire whereUpdatedAt($value)
 */
	class Commentaire extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read \App\Models\Wilaya|null $wilaya
 * @method static \Illuminate\Database\Eloquent\Builder|Commune newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Commune newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Commune query()
 */
	class Commune extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $nom
 * @property string|null $description
 * @property array $regles_json
 * @property array $bareme_navette_json
 * @property \Illuminate\Support\Carbon $date_debut
 * @property \Illuminate\Support\Carbon|null $date_fin
 * @property bool $active
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain query()
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain whereBaremeNavetteJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain whereDateDebut($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain whereDateFin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain whereNom($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain whereReglesJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ConfigurationGain whereUpdatedAt($value)
 */
	class ConfigurationGain extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string|null $message
 * @property string $user_id
 * @property string|null $drivers_license
 * @property string|null $drivers_license_url
 * @property string|null $matricule_engins
 * @property string|null $vehicule
 * @property string|null $vehicule_url
 * @property string|null $vehicule_type
 * @property string $id_card_type
 * @property string $id_card_number
 * @property string|null $id_card_image
 * @property string|null $id_card_image_url
 * @property string|null $id_card_expiry_date
 * @property \Illuminate\Support\Carbon|null $date
 * @property string|null $info
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Livreur|null $livreur
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion query()
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereDriversLicense($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereDriversLicenseUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereIdCardExpiryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereIdCardImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereIdCardImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereIdCardNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereIdCardType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereInfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereMatriculeEngins($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereVehicule($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereVehiculeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeAdhesion whereVehiculeUrl($value)
 */
	class DemandeAdhesion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $client_id
 * @property string|null $wilaya_depot
 * @property string|null $commune_depot
 * @property string $destinataire_id
 * @property string $colis_id
 * @property string $addresse_depot
 * @property string|null $addresse_delivery
 * @property string|null $info_additionnel
 * @property float $prix
 * @property float|null $lat_depot
 * @property float|null $lng_depot
 * @property float|null $lat_delivery
 * @property string|null $wilaya
 * @property string|null $commune
 * @property float|null $lng_delivery
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Client $client
 * @property-read \App\Models\Colis $colis
 * @property-read \App\Models\Client $destinataire
 * @property-read \App\Models\Livraison|null $livraison
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison query()
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereAddresseDelivery($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereAddresseDepot($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereColisId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereCommune($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereCommuneDepot($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereDestinataireId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereInfoAdditionnel($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereLatDelivery($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereLatDepot($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereLngDelivery($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereLngDepot($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison wherePrix($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereWilaya($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DemandeLivraison whereWilayaDepot($value)
 */
	class DemandeLivraison extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $livreur_id
 * @property string $livraison_id
 * @property string|null $navette_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $montant_brut
 * @property string $frais_navette
 * @property string $frais_hub
 * @property string $frais_point_relais
 * @property string $commission_partenaire1
 * @property string $commission_partenaire2
 * @property string $montant_societe_mere
 * @property string $montant_net_livreur
 * @property string $periode
 * @property string $statut_paiement
 * @property \Illuminate\Support\Carbon|null $date_paiement
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read float $total_deductions
 * @property-read \App\Models\Livraison $livraison
 * @property-read \App\Models\Livreur $livreur
 * @property-read \App\Models\Navette|null $navette
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur byLivreur($livreurId)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur byMois($mois)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur byPeriode($debut, $fin)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur nonPayes()
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur payes()
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur query()
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereCommissionPartenaire1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereCommissionPartenaire2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereDatePaiement($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereFraisHub($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereFraisNavette($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereFraisPointRelais($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereLivraisonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereLivreurId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereMontantBrut($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereMontantNetLivreur($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereMontantSocieteMere($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereNavetteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur wherePeriode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereStatutPaiement($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GainLivreur whereUpdatedAt($value)
 */
	class GainLivreur extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $user_id
 * @property string $wilaya_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CodePromo> $codesPromo
 * @property-read int|null $codes_promo_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|Gestionnaire actif()
 * @method static \Illuminate\Database\Eloquent\Builder|Gestionnaire byWilaya($wilayaId)
 * @method static \Illuminate\Database\Eloquent\Builder|Gestionnaire newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Gestionnaire newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Gestionnaire query()
 * @method static \Illuminate\Database\Eloquent\Builder|Gestionnaire whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gestionnaire whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gestionnaire whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gestionnaire whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gestionnaire whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gestionnaire whereWilayaId($value)
 */
	class Gestionnaire extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $client_id
 * @property string|null $demande_livraisons_id
 * @property string|null $livreur_distributeur_id
 * @property string|null $livreur_ramasseur_id
 * @property string|null $bordereau_id
 * @property string|null $navette_id
 * @property string $code_pin
 * @property \Illuminate\Support\Carbon|null $date_ramassage
 * @property \Illuminate\Support\Carbon|null $date_livraison
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Bordereau|null $bordereau
 * @property-read \App\Models\Client $client
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Commentaire> $commentaires
 * @property-read int|null $commentaires_count
 * @property-read \App\Models\DemandeLivraison|null $demandeLivraison
 * @property-read \App\Models\Livreur|null $livreurDistributeur
 * @property-read \App\Models\Livreur|null $livreurRamasseur
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison query()
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereBordereauId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereCodePin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereDateLivraison($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereDateRamassage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereDemandeLivraisonsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereLivreurDistributeurId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereLivreurRamasseurId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereNavetteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livraison whereUpdatedAt($value)
 */
	class Livraison extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $user_id
 * @property string|null $demande_adhesions_id
 * @property string $type
 * @property bool $desactiver
 * @property string|null $wilaya_id Code wilaya du livreur (01 à 58)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Commentaire> $commentaires
 * @property-read int|null $commentaires_count
 * @property-read \App\Models\DemandeAdhesion|null $demandeAdhesion
 * @property-read string $nom_complet
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Livraison> $livraisonsDistribution
 * @property-read int|null $livraisons_distribution_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Livraison> $livraisonsRamassage
 * @property-read int|null $livraisons_ramassage_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur actif()
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur byWilaya($wilayaId)
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur inactif()
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur ofType($type)
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur query()
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur whereDemandeAdhesionsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur whereDesactiver($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Livreur whereWilayaId($value)
 */
	class Livreur extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $reference
 * @property \Illuminate\Support\Carbon $heure_depart
 * @property \Illuminate\Support\Carbon $heure_arrivee
 * @property string $wilaya_depart_id
 * @property string|null $wilaya_transit_id
 * @property string $wilaya_arrivee_id
 * @property string|null $chauffeur_id
 * @property string|null $vehicule_immatriculation
 * @property int $capacite_max
 * @property string $status
 * @property \Illuminate\Support\Carbon $date_depart
 * @property \Illuminate\Support\Carbon $date_arrivee_prevue
 * @property \Illuminate\Support\Carbon|null $date_arrivee_reelle
 * @property string $prix_base
 * @property string $prix_par_colis
 * @property string|null $distance_km
 * @property string|null $carburant_estime
 * @property string|null $peages_estimes
 * @property string $created_by
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Livreur|null $chauffeur
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Colis> $colis
 * @property-read int|null $colis_count
 * @property-read \App\Models\User $createur
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GainLivreur> $gains
 * @property-read int|null $gains_count
 * @property-read string|null $duree_reelle
 * @property-read int $nb_colis
 * @property-read float $poids_total
 * @property-read float $taux_remplissage
 * @property-read float $valeur_totale
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Livraison> $livraisons
 * @property-read int|null $livraisons_count
 * @property-read \App\Models\Wilaya|null $wilayaArrivee
 * @property-read \App\Models\Wilaya|null $wilayaDepart
 * @property-read \App\Models\Wilaya|null $wilayaTransit
 * @method static \Illuminate\Database\Eloquent\Builder|Navette byDate($date)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette byPeriode($debut, $fin)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette byStatus($status)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette byWilayaArrivee($wilayaId)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette byWilayaDepart($wilayaId)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette enCours()
 * @method static \Illuminate\Database\Eloquent\Builder|Navette newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Navette newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Navette onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Navette planifiees()
 * @method static \Illuminate\Database\Eloquent\Builder|Navette query()
 * @method static \Illuminate\Database\Eloquent\Builder|Navette terminees()
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereCapaciteMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereCarburantEstime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereChauffeurId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereDateArriveePrevue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereDateArriveeReelle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereDateDepart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereDistanceKm($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereHeureArrivee($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereHeureDepart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette wherePeagesEstimes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette wherePrixBase($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette wherePrixParColis($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereVehiculeImmatriculation($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereWilayaArriveeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereWilayaDepartId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette whereWilayaTransitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Navette withChauffeurDisponible()
 * @method static \Illuminate\Database\Eloquent\Builder|Navette withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Navette withoutTrashed()
 */
	class Navette extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $user_id
 * @property string $token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder|NotificationToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|NotificationToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|NotificationToken onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|NotificationToken query()
 * @method static \Illuminate\Database\Eloquent\Builder|NotificationToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NotificationToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NotificationToken whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NotificationToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NotificationToken whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NotificationToken withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|NotificationToken withoutTrashed()
 */
	class NotificationToken extends \Eloquent {}
}

namespace App\Models{
/**
 * @method static \Illuminate\Database\Eloquent\Builder|OptimisationTrajet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OptimisationTrajet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OptimisationTrajet query()
 */
	class OptimisationTrajet extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $email
 * @property string $token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetToken query()
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetToken valid()
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetToken whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PasswordResetToken whereToken($value)
 */
	class PasswordResetToken extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @method static \Illuminate\Database\Eloquent\Builder|Permission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission query()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereUpdatedAt($value)
 */
	class Permission extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $message
 * @property string $admin_id
 * @property string $avis_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $admin
 * @property-read \App\Models\Avis $avis
 * @method static \Illuminate\Database\Eloquent\Builder|ResponseAvis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ResponseAvis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ResponseAvis query()
 * @method static \Illuminate\Database\Eloquent\Builder|ResponseAvis whereAdminId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResponseAvis whereAvisId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResponseAvis whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResponseAvis whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResponseAvis whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResponseAvis whereUpdatedAt($value)
 */
	class ResponseAvis extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereUpdatedAt($value)
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $nom
 * @property string $prenom
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property mixed $password
 * @property string|null $telephone
 * @property string|null $photo
 * @property string|null $photo_url
 * @property float|null $latitude
 * @property float|null $longitude
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int $actif
 * @property string|null $role
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Avis> $avis
 * @property-read int|null $avis_count
 * @property-read \App\Models\Client|null $client
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Commentaire> $commentaires
 * @property-read int|null $commentaires_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DemandeAdhesion> $demandeAdhesion
 * @property-read int|null $demande_adhesion_count
 * @property-read \App\Models\Gestionnaire|null $gestionnaire
 * @property-read \App\Models\Livreur|null $livreur
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereActif($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereNom($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePhoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePhotoUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePrenom($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereTelephone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|User withoutTrashed()
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Commune> $communes
 * @property-read int|null $communes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Livreur> $livreurs
 * @property-read int|null $livreurs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Navette> $navettesArrivee
 * @property-read int|null $navettes_arrivee_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Navette> $navettesDepart
 * @property-read int|null $navettes_depart_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Navette> $navettesTransit
 * @property-read int|null $navettes_transit_count
 * @method static \Illuminate\Database\Eloquent\Builder|Wilaya newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Wilaya newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Wilaya query()
 */
	class Wilaya extends \Eloquent {}
}

namespace App\Models{
/**
 * @method static \Illuminate\Database\Eloquent\Builder|notificationHistorique newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|notificationHistorique newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|notificationHistorique query()
 */
	class notificationHistorique extends \Eloquent {}
}

