import { useCallback, useEffect, useMemo, useState } from "react";
import { Check, Pencil, Plus, RotateCcw, Trash2, X } from "lucide-react";
import api from "../api/axios";
import { useAuth } from "../context/AuthContext";
import { useToast } from "../context/ToastContext";
import { useNavigate, useSearchParams } from "react-router-dom";
import { getPageCache, setPageCache } from "../lib/pageCache";
import "../styles/Consommation.css";

const CACHE_KEY = "bons-commande";

const fmt = (value) =>
  new Intl.NumberFormat("fr-MA", { maximumFractionDigits: 0 }).format(
    Number(value || 0),
  );
const bonStatusClass = {
  brouillon: "bc-status-draft",
  envoye: "bc-status-sent",
  reception: "bc-status-received",
  validation: "bc-status-validation",
  paiement: "bc-status-payment",
  reglee: "bc-status-paid",
};
const emptyForm = {
  ligne_budget_id: "",
  numero_bc: "Automatique",
  intitule_bc: "",
  montant_bc: "",
  date_achat: new Date().toISOString().slice(0, 10),
  fournisseur: "",
  fournisseur_id: "",
  annuite: String(new Date().getFullYear()),
  gestion_depassement: "bloquer",
  mode_repartition: "bons_multiples",
  type_paiement: "Virement bancaire",
  description: "",
  statut: "brouillon",
};
const defaultStatuses = ["brouillon", "envoye", "reception", "validation", "paiement", "reglee"].map((libelle) => ({ libelle, default: true }));

export default function BonsCommande() {
  const { hasPermission, canViewAllDepartments, departements } = useAuth();
  const { showToast } = useToast();
  const cached = getPageCache(CACHE_KEY);
  const [bons, setBons] = useState(cached?.bons ?? []);
  const [lignes, setLignes] = useState(cached?.lignes ?? []);
  const [fournisseurs, setFournisseurs] = useState(cached?.fournisseurs ?? []);
  const [statuses, setStatuses] = useState(cached?.statuses ?? defaultStatuses);
  const [addingStatus, setAddingStatus] = useState(false);
  const [newStatus, setNewStatus] = useState({ libelle: "", couleur: "#64748B" });
  const [annuitesDisponibles, setAnnuitesDisponibles] = useState([]);
  const [filter, setFilter] = useState("");
  const [search, setSearch] = useState("");
  const [fournisseurFilter, setFournisseurFilter] = useState("");
  const [departementFilter, setDepartementFilter] = useState("");
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(!cached);
  const [modal, setModal] = useState(false);
  const [editingBon, setEditingBon] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const loadData = useCallback(async () => {
    try {
      const [{ data: b }, { data: l }, { data: f }, { data: customStatuses }] = await Promise.all([
        api.get("/bons-commande"),
        api.get("/ligne-budgets"),
        api.get("/fournisseurs"),
        api.get("/statuts-personnalises?type=bon_commande"),
      ]);
      const nextStatuses = [...defaultStatuses, ...customStatuses];
      setBons(b);
      setLignes(l);
      setFournisseurs(f);
      setStatuses(nextStatuses);
      setPageCache(CACHE_KEY, {
        bons: b,
        lignes: l,
        fournisseurs: f,
        statuses: nextStatuses,
      });
    } catch (e) {
      showToast(
        e.response?.data?.message ||
          "Impossible de charger les bons de commande.",
        true,
      );
    } finally {
      setLoading(false);
    }
  }, [showToast]);
  useEffect(() => {
    loadData();
  }, [loadData]);
  useEffect(() => {
    if (!modal || editingBon) return;
    api
      .get("/references/next/bon-commande")
      .then(({ data }) =>
        setForm((current) => ({ ...current, numero_bc: data.reference })),
      );
  }, [modal, editingBon]);
  useEffect(() => {
    if (!modal || !form.ligne_budget_id) {
      setAnnuitesDisponibles([]);
      return;
    }
    api
      .get(`/ligne-budgets/${form.ligne_budget_id}/annuites`)
      .then(({ data }) => setAnnuitesDisponibles(data))
      .catch(() => setAnnuitesDisponibles([]));
  }, [modal, form.ligne_budget_id]);
  const lineFilter = searchParams.get("ligne") || "";
  const visibleBons = useMemo(
    () =>
      bons.filter(
        (bon) =>
          (!filter || bon.statut === filter) &&
          (!lineFilter || String(bon.ligne_budget_id) === lineFilter) &&
          (!fournisseurFilter ||
            String(bon.fournisseur_id) === fournisseurFilter) &&
          (!departementFilter ||
            String(bon.departement_id) === departementFilter) &&
          (!search ||
            [bon.numero_bc, bon.intitule_bc, bon.fournisseur, bon.ligne_budget?.code]
              .filter(Boolean)
              .some((v) => v.toLowerCase().includes(search.trim().toLowerCase()))),
      ),
    [bons, filter, lineFilter, fournisseurFilter, departementFilter, search],
  );
  const closeModal = () => {
    if (!submitting) setModal(false);
  };
  const addStatus = async () => {
    if (!newStatus.libelle.trim()) return;
    try {
      const { data } = await api.post("/statuts-personnalises", { type: "bon_commande", libelle: newStatus.libelle.trim(), couleur: newStatus.couleur });
      setStatuses((current) => current.some((status) => status.libelle === data.libelle) ? current : [...current, data]);
      setForm((current) => ({ ...current, statut: data.libelle }));
      setNewStatus({ libelle: "", couleur: "#64748B" });
      setAddingStatus(false);
    } catch (e) {
      showToast(e.response?.data?.message || "Ajout du statut impossible.", true);
    }
  };
  const deleteStatus = async () => {
    const status = statuses.find((item) => item.libelle === form.statut);
    if (!status || status.default || !window.confirm(`Supprimer le statut « ${status.libelle} » ?`)) return;
    try {
      await api.delete(`/statuts-personnalises/${status.id}`);
      setStatuses((current) => current.filter((item) => item.id !== status.id));
      setForm((current) => ({ ...current, statut: "brouillon" }));
    } catch (e) { showToast(e.response?.data?.message || "Suppression du statut impossible.", true); }
  };
  const openCreateModal = () => {
    setEditingBon(null);
    setForm(emptyForm);
    setErrors({});
    setModal(true);
  };
  const openEditModal = (bon) => {
    setEditingBon(bon);
    setForm({
      ligne_budget_id: String(bon.ligne_budget_id),
      numero_bc: bon.numero_bc || "",
      intitule_bc: bon.intitule_bc || "",
      montant_bc: String(bon.montant_bc ?? ""),
      date_achat: bon.date_achat || "",
      fournisseur: bon.fournisseur || "",
      fournisseur_id: bon.fournisseur_id ? String(bon.fournisseur_id) : "",
      annuite: bon.annuite ? String(bon.annuite) : String(new Date(bon.date_achat).getFullYear()),
      gestion_depassement: bon.gestion_depassement || "bloquer",
      mode_repartition: bon.mode_repartition || "bons_multiples",
      type_paiement: bon.type_paiement || "Virement bancaire",
      description: bon.description || "",
      statut: bon.statut || "brouillon",
    });
    setErrors({});
    setModal(true);
  };
  const submit = async (event) => {
    event.preventDefault();
    setSubmitting(true);
    setErrors({});
    try {
      const payload = {
        ...form,
        ligne_budget_id: Number(form.ligne_budget_id),
        montant_bc: Number(form.montant_bc),
      };
      if (editingBon) {
        await api.put(`/bons-commande/${editingBon.id}`, payload);
        showToast("Bon de commande mis à jour.");
      } else {
        const { data } = await api.post("/bons-commande", payload);
        showToast(data?.message || "Bon de commande créé au statut brouillon.");
      }
      setModal(false);
      await loadData();
    } catch (e) {
      if (e.response?.status === 422)
        setErrors(
          e.response.data.errors || { form: [e.response.data.message] },
        );
      else showToast(e.response?.data?.message || "Création impossible.", true);
    } finally {
      setSubmitting(false);
    }
  };
  const validate = async (bon) => {
    if (
      !window.confirm(
        `Valider le bon « ${bon.numero_bc} » ? Il ne pourra plus être modifié.`,
      )
    )
      return;
    try {
      await api.post(`/bons-commande/${bon.id}/validate`);
      showToast("Bon de commande validé.");
      await loadData();
    } catch (e) {
      showToast(e.response?.data?.message || "Validation impossible.", true);
    }
  };
  const unvalidate = async (bon) => {
    if (!window.confirm(`Annuler la validation du bon « ${bon.numero_bc} » ?`))
      return;
    try {
      await api.post(`/bons-commande/${bon.id}/unvalidate`);
      showToast("Bon de commande remis en brouillon.");
      await loadData();
    } catch (e) {
      showToast(e.response?.data?.message || "Annulation impossible.", true);
    }
  };
  const deleteBon = async (bon) => {
    if (
      !window.confirm(
        `Supprimer le bon « ${bon.numero_bc} » ? Cette action est irréversible.`,
      )
    )
      return;
    try {
      await api.delete(`/bons-commande/${bon.id}`);
      showToast("Bon de commande supprimé.");
      await loadData();
    } catch (e) {
      showToast(e.response?.data?.message || "Suppression impossible.", true);
    }
  };
  if (loading)
    return (
      <div className="consumption-loading">
        Chargement des bons de commande…
      </div>
    );
  return (
    <div className="consumption-page">
      <div className="consumption-head">
        <div>
          <h1>Bons de commande</h1>
          <p>
            Suivez les engagements de dépense et validez les bons prêts à être
            traités.
          </p>
        </div>
        {hasPermission("bc.create") && (
          <button
            className="btn-primary"
            onClick={() => {
              openCreateModal();
            }}
          >
            <Plus size={16} /> Nouveau bon
          </button>
        )}
      </div>
      <section className="panel">
        <div className="consumption-toolbar">
          <div className="filters-bar">
            <input
              type="text"
              className="search-input"
              placeholder="Rechercher un bon"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            <select
              className="filter-select"
              value={filter}
              onChange={(e) => setFilter(e.target.value)}
            >
              <option value="">Tous les statuts</option>
              {statuses.map((status) => <option key={status.libelle} value={status.libelle}>{status.libelle}</option>)}
            </select>
            <select
              className="filter-select"
              value={lineFilter}
              onChange={(e) =>
                setSearchParams(e.target.value ? { ligne: e.target.value } : {})
              }
            >
              <option value="">Toutes les lignes</option>
              {lignes.map((line) => (
                <option key={line.id} value={line.id}>
                  {line.code}
                </option>
              ))}
            </select>
            <select
              className="filter-select"
              value={fournisseurFilter}
              onChange={(e) => setFournisseurFilter(e.target.value)}
            >
              <option value="">Tous les fournisseurs</option>
              {fournisseurs.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.nom}
                </option>
              ))}
            </select>
            {canViewAllDepartments() && (
              <select
                className="filter-select"
                value={departementFilter}
                onChange={(e) => setDepartementFilter(e.target.value)}
              >
                <option value="">Tous les départements</option>
                {departements.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.nom}
                  </option>
                ))}
              </select>
            )}
          </div>
          <span>{visibleBons.length} bon(s) visible(s)</span>
        </div>
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>N° BC</th>
                <th>Intitulé</th>
                <th>Fournisseur</th>
                <th>Ligne</th>
                <th className="num">Montant global</th>
                <th className="num">Montant facturé</th>
                <th>Date</th>
                <th>Statut</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {visibleBons.length ? (
                visibleBons.map((bon) => (
                  <tr key={bon.id}>
                    <td className="mono">
                      <strong>{bon.numero_bc}</strong>
                      {bon.repartition_total > 1 && (
                        <span className="badge neutral" style={{ marginLeft: 6 }}>
                          tranche {bon.repartition_ordre}/{bon.repartition_total}
                        </span>
                      )}
                      {bon.mode_repartition === "bon_unique" && bon.tranches?.length > 1 && (
                        <span className="badge neutral" style={{ marginLeft: 6 }}>
                          réparti sur {bon.tranches.length} annuités
                        </span>
                      )}
                    </td>
                    <td>
                      {bon.intitule_bc}
                      {bon.mode_repartition === "bon_unique" && bon.tranches?.length > 1 && (
                        <details style={{ marginTop: 4 }}>
                          <summary className="table-subtitle" style={{ cursor: "pointer" }}>
                            Détail des annuités
                          </summary>
                          <ul style={{ margin: "4px 0 0", paddingLeft: 16 }}>
                            {bon.tranches.map((tranche) => (
                              <li key={tranche.id} className="table-subtitle">
                                {tranche.annuite} : {fmt(tranche.montant)} MAD
                              </li>
                            ))}
                          </ul>
                        </details>
                      )}
                    </td>
                    <td>{bon.fournisseur}</td>
                    <td className="mono">{bon.ligne_budget?.code || "—"}</td>
                    <td className="num">{fmt(bon.montant_bc)} MAD</td>
                    <td className="num">
                      <strong>{fmt(bon.montant_consomme)} MAD</strong>
                      <span className="table-subtitle">Reste : {fmt(Number(bon.montant_bc) - Number(bon.montant_consomme || 0))} MAD</span>
                    </td>
                    <td>
                      {bon.date_achat
                        ? new Date(bon.date_achat).toLocaleDateString("fr-FR")
                        : "—"}
                    </td>
                    <td>
                      <span
                        className={`badge ${bonStatusClass[bon.statut] || "neutral"}`}
                        style={statuses.find((status) => status.libelle === bon.statut)?.couleur ? { backgroundColor: statuses.find((status) => status.libelle === bon.statut).couleur, color: "#fff" } : undefined}
                      >
                        {bon.statut}
                      </span>
                    </td>
                    <td>
                      <button className="btn-ghost btn-mini" onClick={() => navigate(`/factures?bon=${bon.id}`)}>Factures ({bon.factures?.length || 0})</button>
                      {hasPermission("bc.edit") && (
                            <button
                              className="btn-ghost btn-mini"
                              onClick={() => openEditModal(bon)}
                            >
                              <Pencil size={14} /> Modifier
                            </button>
                          )}
                      {bon.statut === "brouillon" && (
                        <>
                          {hasPermission("bc.validate") && (
                          <button
                            className="btn-ghost btn-mini"
                            onClick={() => validate(bon)}
                          >
                            <Check size={14} /> Valider
                          </button>
                          )}
                        </>
                      )}
                      {bon.statut === "envoye" && hasPermission("bc.unvalidate") && (
                        <button
                          className="btn-ghost btn-mini"
                          onClick={() => unvalidate(bon)}
                        >
                          <RotateCcw size={14} /> Annuler la validation
                        </button>
                      )}
                      {hasPermission("bc.delete") && (
                        <button
                          className="btn-ghost btn-mini btn-danger"
                          onClick={() => deleteBon(bon)}
                        >
                          <Trash2 size={14} /> Supprimer
                        </button>
                      )}
                    </td>
                  </tr>
                ))
              ) : (
                <tr className="empty-row">
                  <td colSpan={9}>Aucun bon de commande.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
      {modal && (
        <div className="modal-overlay active" onMouseDown={closeModal}>
          <div
            className="modal consumption-modal"
            role="dialog"
            aria-modal="true"
            onMouseDown={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <div>
                <h2>{editingBon ? "Modifier le bon de commande" : "Nouveau bon de commande"}</h2>
                <p>
                  Le montant ne peut pas dépasser le disponible de la ligne
                  sélectionnée.
                </p>
              </div>
              <button className="modal-close" onClick={closeModal}>
                <X size={18} />
              </button>
            </div>
            <form onSubmit={submit}>
              <div className="modal-body">
                <div className="form-grid">
                  <div className="form-field full">
                    <label>
                      Ligne budgétaire <span className="req">*</span>
                    </label>
                    <select
                      value={form.ligne_budget_id}
                      onChange={(e) =>
                        setForm({
                          ...form,
                          ligne_budget_id: e.target.value,
                          annuite: String(new Date(form.date_achat).getFullYear()),
                        })
                      }
                      required
                    >
                      <option value="">Sélectionner une ligne</option>
                      {lignes.map((line) => (
                        <option key={line.id} value={line.id}>
                          {line.code} — {line.intitule}
                        </option>
                      ))}
                    </select>
                    {errors.ligne_budget_id && (
                      <span className="field-error">
                        {errors.ligne_budget_id[0]}
                      </span>
                    )}
                  </div>
                  <div className="form-field">
                    <label>
                      N° BC <span className="req">*</span>
                    </label>
                    <input
                      value={form.numero_bc}
                      onChange={(e) =>
                        setForm({ ...form, numero_bc: e.target.value })
                      }
                      required
                    />
                    {errors.numero_bc && (
                      <span className="field-error">{errors.numero_bc[0]}</span>
                    )}
                  </div>
                  <div className="form-field">
                    <label>
                      Date <span className="req">*</span>
                    </label>
                    <input
                      type="date"
                      value={form.date_achat}
                      onChange={(e) =>
                        setForm({ ...form, date_achat: e.target.value })
                      }
                      required
                    />
                  </div>
                  <div className="form-field full">
                    <label>
                      Intitulé <span className="req">*</span>
                    </label>
                    <input
                      value={form.intitule_bc}
                      onChange={(e) =>
                        setForm({ ...form, intitule_bc: e.target.value })
                      }
                      required
                    />
                  </div>
                  <div className="form-field">
                    <label>
                      Fournisseur <span className="req">*</span>
                    </label>
                    <select
                      value={form.fournisseur_id || ""}
                      onChange={(e) => {
                        const fournisseur = fournisseurs.find(
                          (item) => String(item.id) === e.target.value,
                        );
                        setForm({
                          ...form,
                          fournisseur_id: e.target.value,
                          fournisseur: fournisseur?.nom || "",
                        });
                      }}
                      required
                    >
                      <option value="">Sélectionner un fournisseur</option>
                      {fournisseurs.map((fournisseur) => (
                        <option key={fournisseur.id} value={fournisseur.id}>
                          {fournisseur.nom}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="form-field">
                    <label>Annuité consommée</label>
                    {form.gestion_depassement === "report_annuite_suivante" ? (
                      <>
                        <select
                          value={form.annuite || ""}
                          onChange={(e) => setForm({ ...form, annuite: e.target.value })}
                          required
                        >
                          <option value="">Choisir l'annuité à consommer</option>
                          {annuitesDisponibles
                            .filter(
                              (annuite) =>
                                Number(annuite.annee) >
                                new Date(form.date_achat).getFullYear(),
                            )
                            .map((annuite) => (
                              <option key={annuite.id} value={annuite.annee}>
                                {annuite.annee} — {fmt(annuite.montant_disponible ?? annuite.montant)} MAD disponibles
                              </option>
                            ))}
                        </select>
                        {form.ligne_budget_id &&
                          annuitesDisponibles.filter(
                            (annuite) =>
                              Number(annuite.annee) >
                              new Date(form.date_achat).getFullYear(),
                          ).length === 0 && (
                            <p className="field-hint">
                              Aucune annuité postérieure à {new Date(form.date_achat).getFullYear()} n'est configurée sur cette ligne budgétaire. Ajoutez-en une dans la fiche de la ligne, ou choisissez un autre mode de gestion du dépassement.
                            </p>
                          )}
                      </>
                    ) : (
                      <input value={form.annuite || ""} readOnly />
                    )}
                  </div>
                  <div className="form-field">
                    <label>En cas de dépassement</label>
                    <select value={form.gestion_depassement || "bloquer"} onChange={(e)=>setForm({...form,gestion_depassement:e.target.value, annuite: e.target.value === "report_annuite_suivante" ? "" : String(new Date(form.date_achat).getFullYear())})}>
                      <option value="bloquer">Demander un choix</option>
                      <option value="surplus">Conserver en surplus</option>
                      <option value="report_annuite_suivante">Reporter sur une annuité suivante</option>
                      {!editingBon && (
                        <option value="repartition_automatique">Répartir automatiquement sur les annuités suivantes</option>
                      )}
                    </select>
                    {form.gestion_depassement === "repartition_automatique" && (
                      <>
                        <p className="field-hint">
                          Le montant sera engagé sur l'annuité de l'année d'achat, puis automatiquement reporté sur les annuités suivantes de la ligne (dans l'ordre) jusqu'à consommation totale.
                        </p>
                        <select
                          value={form.mode_repartition || "bons_multiples"}
                          onChange={(e) => setForm({ ...form, mode_repartition: e.target.value })}
                          style={{ marginTop: 6 }}
                        >
                          <option value="bons_multiples">Créer un bon distinct par annuité (plusieurs numéros de BC)</option>
                          <option value="bon_unique">Garder un seul bon de commande (détail des annuités visible dans le bon)</option>
                        </select>
                        <p className="field-hint">
                          {form.mode_repartition === "bon_unique"
                            ? "Un seul numéro de BC sera créé pour le montant total ; la répartition par annuité restera consultable dans le détail de ce bon."
                            : "Un bon de commande distinct (numéro propre) sera créé pour chaque annuité concernée, tous liés entre eux."}
                        </p>
                      </>
                    )}
                  </div>
                  <div className="form-field">
                    <label>
                      Montant (MAD) <span className="req">*</span>
                    </label>
                    <input
                      type="number"
                      min="0"
                      step=".01"
                      value={form.montant_bc}
                      onChange={(e) =>
                        setForm({ ...form, montant_bc: e.target.value })
                      }
                      required
                    />
                  </div>
                  <div className="form-field">
                    <label>Paiement</label>
                    <select
                      value={form.type_paiement}
                      onChange={(e) =>
                        setForm({ ...form, type_paiement: e.target.value })
                      }
                    >
                      <option>Virement bancaire</option>
                      <option>Chèque</option>
                      <option>Espèces</option>
                    </select>
                  </div>
                  <div className="form-field">
                    <label>Statut</label>
                    <div style={{ display: "flex", gap: 8 }}>
                      <select value={form.statut} onChange={(e) => setForm({ ...form, statut: e.target.value })}>
                        {statuses.map((status) => <option key={status.libelle} value={status.libelle}>{status.libelle}</option>)}
                      </select>
                      <button type="button" className="btn-ghost btn-mini" onClick={() => setAddingStatus((value) => !value)}>+ Ajouter</button>
                      {!statuses.find((status) => status.libelle === form.statut)?.default && <button type="button" className="btn-ghost btn-mini btn-danger" onClick={deleteStatus}>Supprimer</button>}
                    </div>
                    {addingStatus && <div style={{ display: "flex", gap: 8, marginTop: 8 }}><input placeholder="Nom" value={newStatus.libelle} onChange={(e) => setNewStatus({ ...newStatus, libelle: e.target.value })} /><input aria-label="Couleur du statut" type="color" value={newStatus.couleur} onChange={(e) => setNewStatus({ ...newStatus, couleur: e.target.value })} /><button type="button" className="btn-primary btn-mini" onClick={addStatus}>Enregistrer</button></div>}
                  </div>
                  <div className="form-field">
                    <label>Description</label>
                    <input
                      value={form.description}
                      onChange={(e) =>
                        setForm({ ...form, description: e.target.value })
                      }
                    />
                  </div>
                  {errors.form && (
                    <span className="field-error">{errors.form[0]}</span>
                  )}
                </div>
              </div>
              <div className="modal-footer">
                <button
                  type="button"
                  className="btn-ghost"
                  onClick={closeModal}
                >
                  Annuler
                </button>
                <button className="btn-primary" disabled={submitting}>
                  {submitting
                    ? editingBon
                      ? "Enregistrement…"
                      : "Création…"
                    : editingBon
                      ? "Enregistrer"
                      : "Créer"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}