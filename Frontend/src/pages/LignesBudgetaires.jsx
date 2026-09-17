import { useCallback, useEffect, useMemo, useState } from "react";
import { Eye, Pencil, Plus, Trash2, X } from "lucide-react";
import { Link, useSearchParams } from "react-router-dom";
import api from "../api/axios";
import { useAuth } from "../context/AuthContext";
import { useToast } from "../context/ToastContext";
import { getPageCache, setPageCache } from "../lib/pageCache";
import "../styles/LignesBudgetaires.css";

const CACHE_KEY = "lignes-budgetaires";

const fmt = (v) =>
  new Intl.NumberFormat("fr-MA", { maximumFractionDigits: 0 }).format(
    Number(v || 0),
  );
const blank = {
  code: "Automatique",
  intitule: "",
  categorie_id: "",
  sous_categorie_id: "",
  budget_id: "",
  montant_alloue: "",
  date_debut_amortissement: new Date().toISOString().slice(0, 10),
  duree_amortissement_annees: "1",
  departement_id: "",
};
export default function LignesBudgetaires() {
  const { showToast } = useToast();
  const { hasPermission, canViewAllDepartments, departements } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const cached = getPageCache(CACHE_KEY);
  const [lignes, setLignes] = useState(cached?.lignes ?? []),
    [cats, setCats] = useState(cached?.cats ?? []),
    [budgets, setBudgets] = useState(cached?.budgets ?? []),
    [modal, setModal] = useState(false),
    [edit, setEdit] = useState(null),
    [form, setForm] = useState(blank),
    [errors, setErrors] = useState({}),
    [search, setSearch] = useState(""),
    [categorieFilter, setCategorieFilter] = useState(""),
    [budgetFilter, setBudgetFilter] = useState(""),
    [statutFilter, setStatutFilter] = useState(""),
    [departementFilter, setDepartementFilter] = useState(""),
    [loading, setLoading] = useState(!cached),
    [saving, setSaving] = useState(false);
  const load = useCallback(async () => {
    try {
      const [l, c, b] = await Promise.all([
        api.get("/ligne-budgets"),
        api.get("/categories"),
        api.get("/budgets"),
      ]);
      setLignes(l.data);
      setCats(c.data);
      setBudgets(b.data);
      setPageCache(CACHE_KEY, { lignes: l.data, cats: c.data, budgets: b.data });
    } catch (e) {
      showToast(
        e.response?.data?.message ||
          "Impossible de charger les lignes budgétaires.",
        true,
      );
    } finally {
      setLoading(false);
    }
  }, [showToast]);
  useEffect(() => {
    load();
  }, [load]);
  useEffect(() => {
    if (!modal || edit) return;
    api
      .get("/references/next/ligne")
      .then(({ data }) =>
        setForm((current) => ({ ...current, code: data.reference })),
      );
  }, [modal, edit]);

  // Sous-catégories disponibles pour la catégorie actuellement sélectionnée
  const sousCategoriesDisponibles = useMemo(
    () =>
      cats.find((c) => String(c.id) === String(form.categorie_id))
        ?.sous_categories || [],
    [cats, form.categorie_id],
  );

  const visibleLignes = useMemo(
    () =>
      lignes.filter(
        (l) =>
          (!categorieFilter ||
            String(l.categorie_id || l.sous_categorie?.categorie_id) ===
              categorieFilter) &&
          (!budgetFilter || String(l.budget_id) === budgetFilter) &&
          (!statutFilter || l.statut === statutFilter) &&
          (!departementFilter ||
            String(l.departement_id) === departementFilter) &&
          (!search ||
            [
              l.code,
              l.intitule,
              l.categorie?.nom,
              l.sous_categorie?.nom,
              l.budget?.code,
            ]
              .filter(Boolean)
              .some((v) => v.toLowerCase().includes(search.trim().toLowerCase()))),
      ),
    [lignes, categorieFilter, budgetFilter, statutFilter, departementFilter, search],
  );

  const close = () => !saving && setModal(false);
  const open = (line = null) => {
    setEdit(line);
    setErrors({});
    setForm(
      line
        ? {
            ...line,
            categorie_id: String(
              line.categorie_id || line.sous_categorie?.categorie_id || "",
            ),
            sous_categorie_id: line.sous_categorie_id
              ? String(line.sous_categorie_id)
              : "",
            montant_alloue: String(line.montant_alloue),
            date_debut_amortissement: line.date_debut_amortissement?.slice(
              0,
              10,
            ),
            duree_amortissement_annees: String(line.duree_amortissement_annees),
          }
        : blank,
    );
    setModal(true);
  };
  useEffect(() => {
    if (loading || searchParams.get("create") !== "1") return;
    const budgetId = searchParams.get("budget"),
      departementId = searchParams.get("departement");
    if (!budgetId || !departementId) return;
    setEdit(null);
    setErrors({});
    setForm({ ...blank, budget_id: budgetId, departement_id: departementId });
    setModal(true);
    setSearchParams({}, { replace: true });
  }, [loading, searchParams, setSearchParams]);
  const save = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      const p = {
        ...form,
        categorie_id: +form.categorie_id,
        sous_categorie_id: form.sous_categorie_id
          ? +form.sous_categorie_id
          : null,
        budget_id: +form.budget_id,
        montant_alloue: +form.montant_alloue,
        duree_amortissement_annees: +form.duree_amortissement_annees,
      };
      if (edit) await api.put(`/ligne-budgets/${edit.id}`, p);
      else await api.post("/ligne-budgets", p);
      showToast(edit ? "Ligne modifiée." : "Ligne créée.");
      setModal(false);
      await load();
    } catch (e) {
      setErrors(
        e.response?.data?.errors || {
          form: [e.response?.data?.message || "Enregistrement impossible."],
        },
      );
    } finally {
      setSaving(false);
    }
  };
  const remove = async (l) => {
    if (!confirm(`Supprimer la ligne « ${l.code} » ?`)) return;
    try {
      await api.delete(`/ligne-budgets/${l.id}`);
      showToast("Ligne supprimée.");
      await load();
    } catch (e) {
      showToast(e.response?.data?.message || "Suppression impossible.", true);
    }
  };
  if (loading)
    return (
      <div className="lines-loading">Chargement des lignes budgétaires…</div>
    );
  return (
    <div className="lines-page">
      <div className="lines-page-head">
        <div>
          <h1>Lignes budgétaires</h1>
          <p>Gérez les allocations, l’amortissement et les annuités.</p>
        </div>
        {hasPermission("ligne.create") && (
          <button className="btn-primary" onClick={() => open()}>
            <Plus size={16} />
            Nouvelle ligne
          </button>
        )}
      </div>
      <section className="panel lines-panel">
        <div className="lines-toolbar">
          <div className="filters-bar">
            <input
              type="text"
              className="search-input"
              placeholder="Rechercher une ligne"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            <select
              className="filter-select"
              value={categorieFilter}
              onChange={(e) => setCategorieFilter(e.target.value)}
            >
              <option value="">Toutes les catégories</option>
              {cats.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nom}
                </option>
              ))}
            </select>
            <select
              className="filter-select"
              value={budgetFilter}
              onChange={(e) => setBudgetFilter(e.target.value)}
            >
              <option value="">Tous les budgets</option>
              {budgets.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.code}
                </option>
              ))}
            </select>
            <select
              className="filter-select"
              value={statutFilter}
              onChange={(e) => setStatutFilter(e.target.value)}
            >
              <option value="">Tous les statuts</option>
              <option value="Disponible">Disponible</option>
              <option value="Partiellement Consommé">
                Partiellement consommé
              </option>
              <option value="Totalement Consommé">Totalement consommé</option>
              <option value="Dépassement">Dépassement</option>
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
          <span>{visibleLignes.length} ligne(s) visible(s)</span>
        </div>
        <div className="table-wrap">
          <table className="lines-table">
            <thead>
              <tr>
                <th>Code / ligne</th>
                <th>Catégorie</th>
                <th>Budget</th>
                <th className="num">Alloué</th>
                <th className="num">Consommé</th>
                <th>Annuités</th>
                <th>Statut</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {visibleLignes.length ? (
                visibleLignes.map((l) => (
                  <tr key={l.id}>
                    <td>
                      <strong className="mono">{l.code}</strong>
                      <span className="line-title">{l.intitule}</span>
                    </td>
                    <td>
                      {l.sous_categorie
                        ? `${l.categorie?.nom || l.sous_categorie.categorie?.nom || "—"} › ${l.sous_categorie.nom}`
                        : l.categorie?.nom || "—"}
                    </td>
                    <td>{l.budget?.code || "—"}</td>
                    <td className="num">{fmt(l.montant_alloue)} MAD</td>
                    <td className="num">{fmt(l.total_consomme)} MAD</td>
                    <td>
                      <div className="annuity-grid">
                        {(l.annuites || []).map((a) => (
                          <span className="annuity-cell" key={a.id}>
                            <b>{a.annee}</b>
                            <small>{fmt(a.montant)}</small>
                          </span>
                        )) || "—"}
                      </div>
                    </td>
                    <td>
                      <span className="badge success">{l.statut}</span>
                    </td>
                    <td className="line-actions">
                      <Link
                        className="btn-ghost btn-mini line-detail-link"
                        to={`/lignes/${l.id}`}
                      >
                        <Eye size={14} />
                        Détail
                      </Link>
                      {hasPermission("ligne.edit") && (
                          <button
                            className="btn-ghost btn-mini"
                            onClick={() => open(l)}
                          >
                            <Pencil size={14} />
                          </button>
                      )}
                      {hasPermission("ligne.delete") && (
                          <button
                            className="btn-ghost btn-mini btn-danger"
                            onClick={() => remove(l)}
                          >
                            <Trash2 size={14} />
                          </button>
                      )}
                    </td>
                  </tr>
                ))
              ) : (
                <tr className="empty-row">
                  <td colSpan={8}>Aucune ligne visible.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
      {modal && (
        <div className="modal-overlay active" onMouseDown={close}>
          <div className="modal" onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div>
                <h2>{edit ? "Modifier" : "Nouvelle"} ligne budgétaire</h2>
              </div>
              <button className="modal-close" onClick={close}>
                <X size={18} />
              </button>
            </div>
            <form onSubmit={save}>
              <div className="modal-body">
                <div className="form-grid">
                  <Field label="Code *">
                    <input
                      value={form.code}
                      onChange={(e) =>
                        setForm({ ...form, code: e.target.value })
                      }
                      required
                    />
                  </Field>
                  <Field label="Budget *">
                    <select
                      value={form.budget_id}
                      onChange={(e) =>
                        setForm({ ...form, budget_id: e.target.value })
                      }
                      required
                    >
                      <option value="" />
                      {budgets.map((b) => (
                        <option value={b.id} key={b.id}>
                          {b.code}
                        </option>
                      ))}
                    </select>
                  </Field>
                  <Field label="Intitulé *" full>
                    <input
                      value={form.intitule}
                      onChange={(e) =>
                        setForm({ ...form, intitule: e.target.value })
                      }
                      required
                    />
                  </Field>
                  <Field label="Catégorie *" full>
                    <select
                      value={form.categorie_id}
                      onChange={(e) =>
                        setForm({
                          ...form,
                          categorie_id: e.target.value,
                          sous_categorie_id: "",
                        })
                      }
                      required
                    >
                      <option value="" />
                      {cats.map((c) => (
                        <option value={c.id} key={c.id}>
                          {c.nom}
                        </option>
                      ))}
                    </select>
                    {errors.categorie_id && (
                      <span className="field-error">
                        {errors.categorie_id[0]}
                      </span>
                    )}
                  </Field>
                  <Field label="Sous-catégorie (optionnel)" full>
                    <select
                      value={form.sous_categorie_id}
                      onChange={(e) =>
                        setForm({
                          ...form,
                          sous_categorie_id: e.target.value,
                        })
                      }
                      disabled={!form.categorie_id}
                    >
                      <option value="">
                        Aucune — rattacher directement à la catégorie
                      </option>
                      {sousCategoriesDisponibles.map((s) => (
                        <option value={s.id} key={s.id}>
                          {s.nom}
                        </option>
                      ))}
                    </select>
                    {errors.sous_categorie_id && (
                      <span className="field-error">
                        {errors.sous_categorie_id[0]}
                      </span>
                    )}
                  </Field>
                  <Field label="Montant *">
                    <input
                      type="number"
                      min="0"
                      value={form.montant_alloue}
                      onChange={(e) =>
                        setForm({ ...form, montant_alloue: e.target.value })
                      }
                      required
                    />
                  </Field>
                  <Field label="Début *">
                    <input
                      type="date"
                      value={form.date_debut_amortissement || ""}
                      onChange={(e) =>
                        setForm({
                          ...form,
                          date_debut_amortissement: e.target.value,
                        })
                      }
                      required
                    />
                  </Field>
                  <Field label="Durée">
                    <input
                      type="number"
                      min="1"
                      max="100"
                      value={form.duree_amortissement_annees}
                      onChange={(e) =>
                        setForm({
                          ...form,
                          duree_amortissement_annees: e.target.value,
                        })
                      }
                    />
                  </Field>
                  {canViewAllDepartments() && (
                    <Field label="Département">
                      <select
                        value={form.departement_id || ""}
                        onChange={(e) =>
                          setForm({ ...form, departement_id: e.target.value })
                        }
                      >
                        <option value="" />
                        {departements.map((d) => (
                          <option key={d.id} value={d.id}>
                            {d.nom}
                          </option>
                        ))}
                      </select>
                    </Field>
                  )}
                  {errors.form && (
                    <span className="field-error">{errors.form[0]}</span>
                  )}
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn-ghost" onClick={close}>
                  Annuler
                </button>
                <button className="btn-primary" disabled={saving}>
                  {saving ? "Enregistrement…" : "Enregistrer"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
function Field({ label, children, full }) {
  return (
    <div className={`form-field ${full ? "full" : ""}`}>
      <label>{label}</label>
      {children}
    </div>
  );
}