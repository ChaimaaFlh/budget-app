import { Paperclip, Pencil, Plus, Trash2, X } from "lucide-react";
import api from "../api/axios";
import { useAuth } from "../context/AuthContext";
import { useToast } from "../context/ToastContext";
import { useSearchParams } from "react-router-dom";
import FactureDocumentsModal from "../components/Factures/FactureDocumentsModal";
import { getPageCache, setPageCache } from "../lib/pageCache";
import "../styles/Consommation.css";
import { useState, useEffect, useCallback, useMemo } from "react";

const CACHE_KEY = "factures";

const fmt = (value) =>
  new Intl.NumberFormat("fr-MA", { maximumFractionDigits: 0 }).format(
    Number(value || 0),
  );
const factureStatus = {
  reception: { label: "Réception", className: "neutral" },
  validation: { label: "Validation", className: "warning" },
  paiement: { label: "Paiement", className: "warning" },
  reglee: { label: "Réglée", className: "success" },
};
const defaultStatuses = ["reception", "validation", "paiement", "reglee"].map((libelle) => ({ libelle, default: true }));
const emptyForm = {
  bon_commande_id: "",
  ref_facture: "",
  montant: "",
  date_reception: new Date().toISOString().slice(0, 10),
  type_reglement: "finale",
  statut: "reception",
  date_paiement: "",
  date_echeance: "",
};

export default function Factures() {
  const { hasPermission } = useAuth();
  const { showToast } = useToast();
  const cached = getPageCache(CACHE_KEY);
  const [bons, setBons] = useState(cached?.bons ?? []);
  const [statuses, setStatuses] = useState(cached?.statuses ?? defaultStatuses);
  const [addingStatus, setAddingStatus] = useState(false);
  const [newStatus, setNewStatus] = useState({ libelle: "", couleur: "#64748B" });
  const [factures, setFactures] = useState(cached?.factures ?? []);
  const [loading, setLoading] = useState(!cached);
  const [modal, setModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [docsFacture, setDocsFacture] = useState(null);
  const [search, setSearch] = useState("");
  const [statutFilter, setStatutFilter] = useState("");
  const [reglementFilter, setReglementFilter] = useState("");
  const [searchParams] = useSearchParams();
  const loadData = useCallback(async () => {
    try {
      const [{ data: b }, { data: customStatuses }] = await Promise.all([
        api.get("/bons-commande"),
        api.get("/statuts-personnalises?type=facture"),
      ]);
      const nextStatuses = [...defaultStatuses, ...customStatuses];
      setStatuses(nextStatuses);
      const batches = await Promise.all(
        b.map(async (bon) => ({
          bon,
          factures: (await api.get(`/bons-commande/${bon.id}/factures`)).data,
        })),
      );
      const nextFactures = batches.flatMap(({ bon, factures: list }) =>
        list.map((facture) => ({ ...facture, bon })),
      );
      setBons(b);
      setFactures(nextFactures);
      setPageCache(CACHE_KEY, {
        bons: b,
        statuses: nextStatuses,
        factures: nextFactures,
      });
    } catch (e) {
      showToast(
        e.response?.data?.message || "Impossible de charger les factures.",
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
    if (!modal || editing) return;
    api
      .get("/references/next/facture")
      .then(({ data }) =>
        setForm((current) => ({ ...current, ref_facture: data.reference })),
      );
  }, [modal]);
  const invoicesByBon = useMemo(
    () =>
      factures.reduce(
        (map, invoice) => ({
          ...map,
          [invoice.bon_commande_id]:
            (map[invoice.bon_commande_id] || 0) + Number(invoice.montant || 0),
        }),
        {},
      ),
    [factures],
  );
  const closeModal = () => {
    if (!submitting) setModal(false);
  };
  const addStatus = async () => {
    if (!newStatus.libelle.trim()) return;
    try {
      const { data } = await api.post("/statuts-personnalises", { type: "facture", libelle: newStatus.libelle.trim(), couleur: newStatus.couleur });
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
      setForm((current) => ({ ...current, statut: "reception" }));
    } catch (e) { showToast(e.response?.data?.message || "Suppression du statut impossible.", true); }
  };
  const submit = async (event) => {
    event.preventDefault();
    setSubmitting(true);
    setErrors({});
    try {
      const { bon_commande_id, ...payload } = form;
      await (editing ? api.put(`/bons-commande/${bon_commande_id}/factures/${editing.id}`, {
        ...payload,
        montant: Number(payload.montant),
      }) : api.post(`/bons-commande/${bon_commande_id}/factures`, {
        ...payload,
        montant: Number(payload.montant),
      }));
      showToast("Facture enregistrée.");
      setModal(false);
      setEditing(null);
      await loadData();
    } catch (e) {
      if (e.response?.status === 422)
        setErrors(
          e.response.data.errors || { form: [e.response.data.message] },
        );
      else
        showToast(
          e.response?.data?.message || "Enregistrement impossible.",
          true,
        );
    } finally {
      setSubmitting(false);
    }
  };
  const openEdit = (invoice) => {
    setEditing(invoice);
    setForm({ bon_commande_id: String(invoice.bon_commande_id), ref_facture: invoice.ref_facture, montant: String(invoice.montant), date_reception: invoice.date_reception || "", type_reglement: invoice.type_reglement, statut: invoice.statut || "reception", date_paiement: invoice.date_paiement || "", date_echeance: invoice.date_echeance || "" });
    setModal(true);
  };
  const remove = async (invoice) => {
    if (!window.confirm(`Supprimer la facture « ${invoice.ref_facture} » ?`))
      return;
    try {
      await api.delete(
        `/bons-commande/${invoice.bon_commande_id}/factures/${invoice.id}`,
      );
      showToast("Facture supprimée.");
      await loadData();
    } catch (e) {
      showToast(e.response?.data?.message || "Suppression impossible.", true);
    }
  };
  const lineFilter = searchParams.get("ligne") || "";
  const bonFilter = searchParams.get("bon") || "";
  const visibleFactures = (
    bonFilter
      ? factures.filter((invoice) => String(invoice.bon_commande_id) === bonFilter)
      : lineFilter
        ? factures.filter(
            (invoice) => String(invoice.bon.ligne_budget_id) === lineFilter,
          )
        : factures
  ).filter(
    (invoice) =>
      (!statutFilter || invoice.statut === statutFilter) &&
      (!reglementFilter || invoice.type_reglement === reglementFilter) &&
      (!search ||
        [invoice.ref_facture, invoice.bon?.numero_bc, invoice.bon?.intitule_bc]
          .filter(Boolean)
          .some((v) => v.toLowerCase().includes(search.trim().toLowerCase()))),
  );
  if (loading)
    return <div className="consumption-loading">Chargement des factures…</div>;
  return (
    <div className="consumption-page">
      <div className="consumption-head">
        <div>
          <h1>Factures</h1>
          <p>
            Suivez les paiements reçus sans dépasser le montant de chaque bon de
            commande.
          </p>
        </div>
        {hasPermission("facture.create") && (
          <button
            className="btn-primary"
            onClick={() => {
              setForm(emptyForm);
              setErrors({});
              setModal(true);
            }}
          >
            <Plus size={16} /> Nouvelle facture
          </button>
        )}
      </div>
      {lineFilter && (
        <p className="context-note">
          Factures filtrées pour une ligne budgétaire.
        </p>
      )}
      <section className="panel">
        <div className="consumption-toolbar">
          <div className="filters-bar">
            <input
              type="text"
              className="search-input"
              placeholder="Rechercher une facture"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            <select
              className="filter-select"
              value={statutFilter}
              onChange={(e) => setStatutFilter(e.target.value)}
            >
              <option value="">Tous les statuts</option>
              {statuses.map((status) => <option key={status.libelle} value={status.libelle}>{status.libelle}</option>)}
            </select>
            <select
              className="filter-select"
              value={reglementFilter}
              onChange={(e) => setReglementFilter(e.target.value)}
            >
              <option value="">Tous les règlements</option>
              <option value="acompte">Acompte</option>
              <option value="finale">Finale</option>
            </select>
          </div>
          <span>{visibleFactures.length} facture(s) visible(s)</span>
        </div>
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Référence</th>
                <th>Bon de commande</th>
                <th className="num">Montant</th>
                <th>Réception</th>
                <th>Règlement</th>
                <th>Statut</th>
                <th>État du bon</th>
                <th>Documents</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {visibleFactures.length ? (
                visibleFactures.map((invoice) => {
                  const billed = invoicesByBon[invoice.bon_commande_id] || 0;
                  const total = Number(invoice.bon.montant_bc || 0);
                  const status = factureStatus[invoice.statut] || { label: invoice.statut, className: "neutral" };
                  return (
                    <tr key={invoice.id}>
                      <td className="mono">
                        <strong>{invoice.ref_facture}</strong>
                      </td>
                      <td>
                        <strong>{invoice.bon.numero_bc}</strong>
                        <span className="table-subtitle">
                          {invoice.bon.intitule_bc}
                        </span>
                      </td>
                      <td className="num">{fmt(invoice.montant)} MAD</td>
                      <td>
                        {invoice.date_reception
                          ? new Date(invoice.date_reception).toLocaleDateString(
                              "fr-FR",
                            )
                          : "—"}
                      </td>
                      <td>
                        <span className="badge neutral">
                          {invoice.type_reglement}
                        </span>
                      </td>
                      <td>
                        <span className={`badge ${status.className}`} style={statuses.find((item) => item.libelle === invoice.statut)?.couleur ? { backgroundColor: statuses.find((item) => item.libelle === invoice.statut).couleur, color: "#fff" } : undefined}>
                          {status.label}
                        </span>
                      </td>
                      <td>
                        <span
                          className={
                            billed >= total
                              ? "invoice-complete"
                              : "invoice-pending"
                          }
                        >
                          {fmt(billed)} / {fmt(total)} MAD
                        </span>
                      </td>
                      <td>
                        <button
                          className="btn-ghost btn-mini"
                          onClick={() => setDocsFacture(invoice)}
                        >
                          <Paperclip size={14} />
                          {invoice.documents_count > 0
                            ? ` ${invoice.documents_count}`
                            : " Ajouter"}
                        </button>
                      </td>
                      <td>
                        {hasPermission("facture.edit") && invoice.bon.statut !== "reglee" && (
                          <button className="btn-ghost btn-mini" onClick={() => openEdit(invoice)}><Pencil size={14} /> Modifier</button>
                        )}
                        {hasPermission("facture.delete") && (
                          <button
                            className="btn-ghost btn-mini btn-danger"
                            onClick={() => remove(invoice)}
                          >
                            <Trash2 size={14} /> Supprimer
                          </button>
                        )}
                      </td>
                    </tr>
                  );
                })
              ) : (
                <tr className="empty-row">
                  <td colSpan={9}>Aucune facture visible.</td>
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
                <h2>{editing ? "Modifier la facture" : "Nouvelle facture"}</h2>
                <p>Le total facturé est contrôlé automatiquement.</p>
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
                      Bon de commande <span className="req">*</span>
                    </label>
                    <select
                      value={form.bon_commande_id}
                      onChange={(e) =>
                        setForm({ ...form, bon_commande_id: e.target.value })
                      }
                      required
                    >
                      <option value="">Sélectionner un bon</option>
                      {bons.map((bon) => (
                        <option key={bon.id} value={bon.id}>
                          {bon.numero_bc} — {bon.intitule_bc}
                        </option>
                      ))}
                    </select>
                    {errors.bon_commande_id && (
                      <span className="field-error">
                        {errors.bon_commande_id[0]}
                      </span>
                    )}
                  </div>
                  <div className="form-field">
                    <label>
                      Référence <span className="req">*</span>
                    </label>
                    <input
                      value={form.ref_facture}
                      onChange={(e) =>
                        setForm({ ...form, ref_facture: e.target.value })
                      }
                      required
                    />
                  </div>
                  <div className="form-field">
                    <label>
                      Montant (MAD) <span className="req">*</span>
                    </label>
                    <input
                      type="number"
                      min="0"
                      step=".01"
                      value={form.montant}
                      onChange={(e) =>
                        setForm({ ...form, montant: e.target.value })
                      }
                      required
                    />
                    {errors.montant && <span className="field-error">{errors.montant[0]}</span>}
                  </div>
                  <div className="form-field">
                    <label>Date de réception</label>
                    <input
                      type="date"
                      value={form.date_reception}
                      onChange={(e) =>
                        setForm({ ...form, date_reception: e.target.value })
                      }
                    />
                  </div>
                  <div className="form-field">
                    <label>
                      Règlement <span className="req">*</span>
                    </label>
                    <select
                      value={form.type_reglement}
                      onChange={(e) =>
                        setForm({ ...form, type_reglement: e.target.value })
                      }
                    >
                      <option value="acompte">Acompte</option>
                      <option value="finale">Finale</option>
                    </select>
                  </div>
                  <div className="form-field"><label>Statut</label><div style={{ display: "flex", gap: 8 }}><select value={form.statut} onChange={(e) => setForm({ ...form, statut: e.target.value })}>{statuses.map((status) => <option key={status.libelle} value={status.libelle}>{status.libelle}</option>)}</select><button type="button" className="btn-ghost btn-mini" onClick={() => setAddingStatus((value) => !value)}>+ Ajouter</button>{!statuses.find((status) => status.libelle === form.statut)?.default && <button type="button" className="btn-ghost btn-mini btn-danger" onClick={deleteStatus}>Supprimer</button>}</div>{addingStatus && <div style={{ display: "flex", gap: 8, marginTop: 8 }}><input placeholder="Nom" value={newStatus.libelle} onChange={(e) => setNewStatus({ ...newStatus, libelle: e.target.value })} /><input aria-label="Couleur du statut" type="color" value={newStatus.couleur} onChange={(e) => setNewStatus({ ...newStatus, couleur: e.target.value })} /><button type="button" className="btn-primary btn-mini" onClick={addStatus}>Enregistrer</button></div>}</div>
                  <div className="form-field"><label>Date de paiement</label><input type="date" value={form.date_paiement || ""} onChange={(e) => setForm({ ...form, date_paiement: e.target.value })} /></div>
                  <div className="form-field"><label>Date d'échéance</label><input type="date" value={form.date_echeance || ""} onChange={(e) => setForm({ ...form, date_echeance: e.target.value })} /></div>
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
                  {submitting ? "Enregistrement…" : "Enregistrer"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
      {docsFacture && (
        <FactureDocumentsModal
          facture={docsFacture}
          canManage={hasPermission("facture.create") || hasPermission("facture.delete")}
          onClose={() => setDocsFacture(null)}
          onChanged={loadData}
        />
      )}
    </div>
  );
}