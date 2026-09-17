import { useCallback, useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { Paperclip, Truck, FileText, Calendar, User } from "lucide-react";
import api from "../api/axios";
import { useToast } from "../context/ToastContext";
import { getPageCache, setPageCache } from "../lib/pageCache";
import "../styles/DetailLigneBudgetaire.css";

const formatAmount = (value) =>
  new Intl.NumberFormat("fr-MA", { maximumFractionDigits: 2 }).format(
    Number(value || 0),
  );

const formatDate = (value) =>
  value ? new Date(value).toLocaleDateString("fr-FR") : null;

/**
 * Montant réellement engagé sur une annuité donnée, tous modes de gestion
 * confondus. Un bon "classique" (ou une tranche issue du mode "plusieurs
 * bons") est compté directement via montant_bc/annuite. Un bon créé en mode
 * "bon unique" porte un montant_bc GLOBAL (toutes annuités confondues) : il
 * ne doit donc jamais être compté directement, mais via le détail de ses
 * tranches (bon.tranches), seule source fiable de la répartition par
 * annuité pour ce type de bon.
 *
 * Cette logique doit rester alignée avec
 * BonCommande::engagementAnnuiteCentimes() côté backend, sous peine de faire
 * afficher des pourcentages incorrects (ex : une année qui dépasse 100% et
 * une autre qui reste à 0% alors qu'elle a bien reçu une part du montant).
 */
function engagementPourAnnuite(bons, annee) {
  return bons.reduce((sum, bon) => {
    if (bon.mode_repartition === "bon_unique") {
      const parTranche = (bon.tranches || [])
        .filter((tranche) => Number(tranche.annuite) === Number(annee))
        .reduce((s, tranche) => s + Number(tranche.montant || 0), 0);
      return sum + parTranche;
    }

    const anneeBon = Number(
      bon.annuite || new Date(bon.date_achat).getFullYear(),
    );
    return anneeBon === Number(annee)
      ? sum + Number(bon.montant_bc || 0)
      : sum;
  }, 0);
}

const FACTURE_STATUTS = {
  reception: { label: "Réception", className: "neutral" },
  validation: { label: "Validation", className: "warning" },
  paiement: { label: "Paiement", className: "warning" },
  reglee: { label: "Réglée", className: "success" },
};

const REGLEMENT_LABELS = {
  acompte: "Acompte",
  finale: "Finale",
};

const BON_STATUTS = {
  brouillon: { label: "Brouillon", className: "bc-status-draft" },
  envoye: { label: "Envoyé", className: "bc-status-sent" },
  reception: { label: "Réception", className: "bc-status-received" },
  validation: { label: "Validation", className: "bc-status-validation" },
  paiement: { label: "Paiement", className: "bc-status-payment" },
  reglee: { label: "Réglée", className: "bc-status-paid" },
};

export default function DetailLigneBudgetaire() {
  const { id } = useParams();
  const { showToast } = useToast();
  const cacheKey = `ligne-detail-${id}`;
  const cached = getPageCache(cacheKey);
  const [line, setLine] = useState(cached?.line ?? null);
  const [annuites, setAnnuites] = useState(cached?.annuites ?? []);
  const [bons, setBons] = useState(cached?.bons ?? []);
  const [factures, setFactures] = useState(cached?.factures ?? []);

  const load = useCallback(async () => {
    try {
      const [lineResponse, annuiteResponse, bonsResponse] = await Promise.all([
        api.get(`/ligne-budgets/${id}`),
        api.get(`/ligne-budgets/${id}/annuites`),
        api.get("/bons-commande"),
      ]);
      const linkedBons = bonsResponse.data.filter(
        (bon) => String(bon.ligne_budget_id) === String(id),
      );
      const invoiceResponses = await Promise.all(
        linkedBons.map((bon) => api.get(`/bons-commande/${bon.id}/factures`)),
      );
      const nextFactures = invoiceResponses.flatMap((response, index) =>
        response.data.map((facture) => ({
          ...facture,
          bon: linkedBons[index],
        })),
      );
      setLine(lineResponse.data);
      setAnnuites(annuiteResponse.data);
      setBons(linkedBons);
      setFactures(nextFactures);
      setPageCache(`ligne-detail-${id}`, {
        line: lineResponse.data,
        annuites: annuiteResponse.data,
        bons: linkedBons,
        factures: nextFactures,
      });
    } catch {
      showToast("Impossible de charger la ligne.", true);
    }
  }, [id, showToast]);

  useEffect(() => {
    load();
  }, [load]);

  const allocated = Number(line?.montant_alloue || 0);

  if (!line) return <div className="line-detail-loading">Chargement…</div>;

  return (
    <div className="line-detail">
      <Link className="back-link" to="/lignes">
        ← Retour aux lignes
      </Link>
      <div className="line-detail-head">
        <div>
          <div className="eyebrow">Ligne budgétaire</div>
          <h1>{line.intitule}</h1>
          <p className="mono">
            {line.code} · Début {line.date_debut_amortissement} ·{" "}
            {line.duree_amortissement_annees} an(s)
          </p>
        </div>
        <span className="badge success">{line.statut}</span>
      </div>
      <div className="line-summary">
        <Summary label="Alloué" value={`${formatAmount(allocated)} MAD`} />
        <Summary
          label="Consommé"
          value={`${formatAmount(line.total_consomme)} MAD`}
        />
        <Summary
          label="Disponible"
          value={`${formatAmount(allocated - Number(line.total_consomme || 0))} MAD`}
        />
        <Summary label="Bons liés" value={bons.length} />
      </div>
      <section className="panel annual-orders-panel">
        <div className="panel-head"><div><h3>Annuités</h3><p>Répartition et consommation par année.</p></div></div>
        <div className="annual-orders-list">{annuites.map((annuite) => {
          const total = engagementPourAnnuite(bons, annuite.annee);
          const percent = Number(annuite.montant) ? (total / Number(annuite.montant)) * 100 : 0;
          const percentAffiche = Math.min(100, Math.round(percent));
          return <article key={annuite.id} className="annual-order-card"><div className="annual-order-summary"><span className="annual-year">{annuite.annee}</span><div><strong>{formatAmount(annuite.montant)} MAD</strong><small>{formatAmount(total)} MAD engagés</small><div className="annual-progress"><i style={{ width: `${Math.min(100, percent)}%` }} /></div></div><b>{percentAffiche}%</b></div></article>;
        })}</div>
      </section>
      <BonsCommandeAssocies bons={bons} link={`/bons?ligne=${id}`} />
      <FacturesAssociees factures={factures} link={`/factures?ligne=${id}`} />
    </div>
  );
}

function Summary({ label, value }) {
  return (
    <div>
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}
function AnnualOrders({ annuites, bons }) {
  return (
    <section className="panel annual-orders-panel">
      <div className="panel-head">
        <div>
          <h3>Bons de commande par annuité</h3>
          <p>Engagements classés selon leur date d’achat.</p>
        </div>
      </div>
      <div className="annual-orders-list">
        {annuites.map((annuite) => {
          const orders = bons.filter(
            (bon) =>
              new Date(bon.date_achat).getFullYear() === Number(annuite.annee),
          );
          const total = orders.reduce(
            (sum, bon) => sum + Number(bon.montant_bc || 0),
            0,
          );
          const rate = Number(annuite.montant)
            ? Math.min(100, (total / Number(annuite.montant)) * 100)
            : 0;
          return (
            <article key={annuite.id} className="annual-order-card">
              <div className="annual-order-summary">
                <span className="annual-year">{annuite.annee}</span>
                <div>
                  <strong>{formatAmount(total)} MAD engagés</strong>
                  <small>
                    {orders.length} bon{orders.length > 1 ? "s" : ""} · plafond{" "}
                    {formatAmount(annuite.montant)} MAD
                  </small>
                  <div className="annual-progress">
                    <i style={{ width: `${rate}%` }} />
                  </div>
                </div>
                <b>{Math.round(rate)}%</b>
              </div>
              {orders.length ? (
                <div className="annual-order-items">
                  {orders.map((bon) => (
                    <div key={bon.id}>
                      <span className="mono">{bon.numero_bc}</span>
                      <strong>{bon.intitule_bc}</strong>
                      <small>
                        {bon.fournisseur} ·{" "}
                        {new Date(bon.date_achat).toLocaleDateString("fr-FR")}
                      </small>
                      <b>{formatAmount(bon.montant_bc)} MAD</b>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="empty-note">
                  Aucun bon de commande enregistré pour cette année.
                </p>
              )}
            </article>
          );
        })}
      </div>
    </section>
  );
}
function BonsCommandeAssocies({ bons, link }) {
  return (
    <section className="panel related-panel orders-panel">
      <div className="panel-head">
        <div>
          <h3>Bons de commande associés</h3>
          <p>
            {bons.length} bon{bons.length > 1 ? "s" : ""} de commande lié
            {bons.length > 1 ? "s" : ""} à cette ligne.
          </p>
        </div>
        <Link className="btn-ghost btn-mini" to={link}>
          Voir tout
        </Link>
      </div>
      {bons.length ? (
        <div className="invoice-list">
          {bons.map((bon) => {
            const status = BON_STATUTS[bon.statut] || BON_STATUTS.brouillon;
            const facturesCount = bon.factures?.length || 0;
            const facture = facturesCount
              ? bon.factures.reduce((sum, f) => sum + Number(f.montant || 0), 0)
              : 0;
            const restant = Number(bon.montant_bc || 0) - facture;

            return (
              <article key={bon.id} className="invoice-card">
                <div className="invoice-card-top">
                  <div className="invoice-card-title">
                    <span className="mono related-reference">
                      {bon.numero_bc}
                    </span>
                    <span className={`badge ${status.className}`}>
                      {status.label}
                    </span>
                    {bon.type_paiement && (
                      <span className="badge neutral">
                        {bon.type_paiement}
                      </span>
                    )}
                  </div>
                  <strong className="invoice-amount">
                    {formatAmount(bon.montant_bc)} MAD
                  </strong>
                </div>

                <p className="invoice-card-subtitle">{bon.intitule_bc}</p>

                <div className="invoice-card-meta">
                  <span>
                    <Truck size={13} />
                    {bon.fournisseur || "Fournisseur inconnu"}
                  </span>
                  <span>
                    <Calendar size={13} />
                    {formatDate(bon.date_achat) || "—"}
                  </span>
                  {bon.user?.name && (
                    <span>
                      <User size={13} />
                      {bon.user.name}
                    </span>
                  )}
                  <span>
                    <FileText size={13} />
                    {facturesCount} facture{facturesCount > 1 ? "s" : ""}{" "}
                    liée{facturesCount > 1 ? "s" : ""}
                  </span>
                </div>

                {facturesCount > 0 && (
                  <div className="invoice-card-dates">
                    <div>
                      <span>Facturé</span>
                      <strong>{formatAmount(facture)} MAD</strong>
                    </div>
                    <div className={restant < 0 ? "invoice-date-warning" : ""}>
                      <span>Restant</span>
                      <strong>{formatAmount(restant)} MAD</strong>
                    </div>
                  </div>
                )}
              </article>
            );
          })}
        </div>
      ) : (
        <div className="related-empty">
          <strong>Aucun bon de commande associé</strong>
          <span>
            Les nouveaux bons de commande apparaîtront ici automatiquement.
          </span>
        </div>
      )}
    </section>
  );
}
function FacturesAssociees({ factures, link }) {
  const today = new Date();

  return (
    <section className="panel related-panel invoices-panel">
      <div className="panel-head">
        <div>
          <h3>Factures associées</h3>
          <p>
            {factures.length} facture{factures.length > 1 ? "s" : ""} liée
            {factures.length > 1 ? "s" : ""} à cette ligne.
          </p>
        </div>
        <Link className="btn-ghost btn-mini" to={link}>
          Voir tout
        </Link>
      </div>
      {factures.length ? (
        <div className="invoice-list">
          {factures.map((facture) => {
            const status =
              FACTURE_STATUTS[facture.statut] || FACTURE_STATUTS.reception;
            const echeance = facture.date_echeance
              ? new Date(facture.date_echeance)
              : null;
            const enRetard =
              echeance && echeance < today && facture.statut !== "reglee";

            return (
              <article key={facture.id} className="invoice-card">
                <div className="invoice-card-top">
                  <div className="invoice-card-title">
                    <span className="mono related-reference">
                      {facture.ref_facture}
                    </span>
                    <span className={`badge ${status.className}`}>
                      {status.label}
                    </span>
                    <span className="badge neutral">
                      {REGLEMENT_LABELS[facture.type_reglement] ||
                        facture.type_reglement}
                    </span>
                    {enRetard && (
                      <span className="badge danger">Échéance dépassée</span>
                    )}
                  </div>
                  <strong className="invoice-amount">
                    {formatAmount(facture.montant)} MAD
                  </strong>
                </div>

                <div className="invoice-card-meta">
                  <span>
                    <Truck size={13} />
                    {facture.bon?.fournisseur || "Fournisseur inconnu"}
                  </span>
                  {facture.bon && (
                    <span>
                      <FileText size={13} />
                      Bon <strong className="mono">{facture.bon.numero_bc}</strong>
                    </span>
                  )}
                  {facture.documents_count > 0 && (
                    <span>
                      <Paperclip size={13} />
                      {facture.documents_count} pièce
                      {facture.documents_count > 1 ? "s" : ""} jointe
                      {facture.documents_count > 1 ? "s" : ""}
                    </span>
                  )}
                </div>

                <div className="invoice-card-dates">
                  <div>
                    <span>Réception</span>
                    <strong>{formatDate(facture.date_reception) || "—"}</strong>
                  </div>
                  <div className={enRetard ? "invoice-date-warning" : ""}>
                    <span>Échéance</span>
                    <strong>{formatDate(facture.date_echeance) || "—"}</strong>
                  </div>
                  <div>
                    <span>Paiement</span>
                    <strong>{formatDate(facture.date_paiement) || "—"}</strong>
                  </div>
                </div>
              </article>
            );
          })}
        </div>
      ) : (
        <div className="related-empty">
          <strong>Aucune facture associée</strong>
          <span>
            Les nouvelles factures apparaîtront ici automatiquement.
          </span>
        </div>
      )}
    </section>
  );
}