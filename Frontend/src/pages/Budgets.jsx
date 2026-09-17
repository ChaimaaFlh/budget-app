import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { ChevronDown, Pencil, Plus, Trash2, X } from "lucide-react";
import { useNavigate } from "react-router-dom";
import api from "../api/axios";
import { useAuth } from "../context/AuthContext";
import { useToast } from "../context/ToastContext";
import { getPageCache, setPageCache } from "../lib/pageCache";
import "../styles/Budgets.css";

const CACHE_KEY = "budgets";

const emptyForm = {
  code: "Automatique",
  nom: "",
  montant_global: "",
  annee: new Date().getFullYear(),
  type: "fonctionnement",
  description: "",
  departement_ids: [],
};
const fmt = (value) =>
  new Intl.NumberFormat("fr-MA", { maximumFractionDigits: 0 }).format(
    Number(value || 0),
  );

export default function Budgets() {
  const { hasPermission, canViewAllDepartments, departements } = useAuth();
  const { showToast } = useToast();
  const navigate = useNavigate();
  const cached = getPageCache(CACHE_KEY);
  const [budgets, setBudgets] = useState(cached?.budgets ?? []);
  const [lignes, setLignes] = useState(cached?.lignes ?? []);
  const [loading, setLoading] = useState(!cached);
  const [modal, setModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [deptDropdownOpen, setDeptDropdownOpen] = useState(false);
  const deptDropdownRef = useRef(null);

  useEffect(() => {
    if (!deptDropdownOpen) return;
    const onClickOutside = (event) => {
      if (
        deptDropdownRef.current &&
        !deptDropdownRef.current.contains(event.target)
      )
        setDeptDropdownOpen(false);
    };
    document.addEventListener("mousedown", onClickOutside);
    return () => document.removeEventListener("mousedown", onClickOutside);
  }, [deptDropdownOpen]);

  const canCreate = canViewAllDepartments() && hasPermission("budget.create");
  const canEdit = canViewAllDepartments() && hasPermission("budget.edit");
  const canDelete = canViewAllDepartments() && hasPermission("budget.delete");
  const canClose = canViewAllDepartments() && hasPermission("budget.close");
  const canManage = canCreate || canEdit || canDelete || canClose;

  const loadData = useCallback(async () => {
    try {
      const [{ data: budgetsData }, { data: lignesData }] = await Promise.all([
        api.get("/budgets"),
        api.get("/ligne-budgets"),
      ]);
      setBudgets(budgetsData);
      setLignes(lignesData);
      setPageCache(CACHE_KEY, { budgets: budgetsData, lignes: lignesData });
    } catch (error) {
      showToast(
        error.response?.data?.message || "Impossible de charger les budgets.",
        true,
      );
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const lineCounts = useMemo(
    () =>
      lignes.reduce(
        (counts, line) => ({
          ...counts,
          [line.budget_id]: (counts[line.budget_id] || 0) + 1,
        }),
        {},
      ),
    [lignes],
  );
  const budgetDepartments = useMemo(() => {
    const departmentNames = new Map(
      departements.map((department) => [String(department.id), department.nom]),
    );

    return lignes.reduce((result, line) => {
      if (!line.budget_id || !line.departement_id) return result;
      const departmentName = departmentNames.get(String(line.departement_id));
      if (!departmentName) return result;

      const budgetId = String(line.budget_id);
      if (!result[budgetId]) result[budgetId] = [];
      if (!result[budgetId].includes(departmentName))
        result[budgetId].push(departmentName);
      return result;
    }, {});
  }, [departements, lignes]);
  const totals = useMemo(
    () =>
      budgets.reduce(
        (acc, budget) => ({
          global: acc.global + Number(budget.montant_global || 0),
          allocated: acc.allocated + Number(budget.total_alloue || 0),
        }),
        { global: 0, allocated: 0 },
      ),
    [budgets],
  );

  const openModal = async (budget = null) => {
    setEditing(budget);
    setErrors({});
    setDeptDropdownOpen(false);
    if (budget)
      setForm({
        code: budget.code,
        nom: budget.nom,
        montant_global: String(budget.montant_global),
        annee: budget.annee,
        type: budget.type,
        description: budget.description || "",
        departement_ids: (budget.departements || []).map((d) => String(d.id)),
      });
    else {
      const { data } = await api.get("/references/next/budget");
      setForm({ ...emptyForm, code: data.reference });
    }
    setModal(true);
  };
  const closeModal = () => {
    if (!submitting) setModal(false);
  };

  const saveBudget = async (event) => {
    event.preventDefault();
    setSubmitting(true);
    setErrors({});
    try {
      const payload = {
        code: form.code,
        nom: form.nom,
        montant_global: Number(form.montant_global),
        annee: Number(form.annee),
        type: form.type,
        description: form.description,
        departement_ids: form.departement_ids.map(Number),
      };
      if (editing) {
        await api.put(`/budgets/${editing.id}`, payload);
        showToast("Budget modifié.");
        setModal(false);
        await loadData();
      } else {
        const { data } = await api.post("/budgets", payload);
        showToast(
          form.departement_ids.length
            ? "Budget créé et attribué aux départements sélectionnés."
            : "Budget créé. Créez maintenant sa première ligne budgétaire pour enregistrer le département.",
        );
        setModal(false);
        navigate(
          `/lignes?create=1&budget=${data.data.id}&departement=${form.departement_ids[0] || ""}`,
        );
      }
    } catch (error) {
      if (error.response?.status === 422)
        setErrors(
          error.response.data.errors || { form: [error.response.data.message] },
        );
      else
        showToast(
          error.response?.data?.message || "Enregistrement impossible.",
          true,
        );
    } finally {
      setSubmitting(false);
    }
  };

  const closeBudget = async (budget) => {
    if (
      !window.confirm(
        `Clôturer le budget « ${budget.code} » ? Les lignes existantes resteront disponibles pour les engagements déjà prévus.`,
      )
    )
      return;
    try {
      await api.post(`/budgets/${budget.id}/close`);
      showToast("Budget clôturé.");
      await loadData();
    } catch (error) {
      showToast(error.response?.data?.message || "Clôture impossible.", true);
    }
  };

  const deleteBudget = async (budget) => {
    if (!window.confirm(`Supprimer le budget « ${budget.code} » ?`)) return;
    try {
      await api.delete(`/budgets/${budget.id}`);
      showToast("Budget supprimé.");
      await loadData();
    } catch (error) {
      showToast(
        error.response?.data?.message || "Suppression impossible.",
        true,
      );
    }
  };

  if (loading)
    return <div className="budgets-loading">Chargement des budgets…</div>;

  return (
    <div className="budgets-page">
      <div className="budgets-page-head">
        <div>
          <h1>Budgets</h1>
          <p>
            Suivez les enveloppes budgétaires, leurs allocations et leur statut
            d’ouverture.
          </p>
        </div>
        {canCreate && (
          <button className="btn-primary" onClick={() => openModal()}>
            <Plus size={16} /> Nouveau budget
          </button>
        )}
      </div>
      <div className="budget-kpis">
        <div className="budget-kpi">
          <span>Budgets visibles</span>
          <strong>{budgets.length}</strong>
        </div>
        <div className="budget-kpi">
          <span>Montant global</span>
          <strong>{fmt(totals.global)} MAD</strong>
        </div>
        <div className="budget-kpi">
          <span>Déjà alloué</span>
          <strong>{fmt(totals.allocated)} MAD</strong>
        </div>
        <div className="budget-kpi">
          <span>Disponible</span>
          <strong>{fmt(totals.global - totals.allocated)} MAD</strong>
        </div>
      </div>
      <section className="panel budgets-panel">
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Code</th>
                <th>Budget</th>
                {canViewAllDepartments() && <th>Département</th>}
                <th>Année</th>
                <th>Lignes</th>
                <th className="num">Montant global</th>
                <th>Allocation</th>
                <th>Statut</th>
                {canManage && <th />}
              </tr>
            </thead>
            <tbody>
              {budgets.length ? (
                budgets.map((budget) => {
                  const total = Number(budget.montant_global || 0);
                  const allocated = Number(budget.total_alloue || 0);
                  const percent = total
                    ? Math.min(100, (allocated / total) * 100)
                    : 0;
                  const directDepartments = (budget.departements || []).map(
                    (d) => d.nom,
                  );
                  const departments = directDepartments.length
                    ? directDepartments
                    : budgetDepartments[String(budget.id)] || [];
                  return (
                    <tr key={budget.id}>
                      <td className="mono">
                        <strong>{budget.code}</strong>
                      </td>
                      <td>
                        <strong>{budget.nom}</strong>
                        <span className="budget-type">{budget.type}</span>
                      </td>
                      {canViewAllDepartments() && (
                        <td>
                          {departments.length ? (
                            <div className="budget-departments">
                              {departments.map((department) => (
                                <span
                                  className="budget-department"
                                  key={department}
                                >
                                  {department}
                                </span>
                              ))}
                            </div>
                          ) : (
                            <span className="budget-department-empty">
                              Non affecté
                            </span>
                          )}
                        </td>
                      )}
                      <td>{budget.annee}</td>
                      <td>{lineCounts[budget.id] || 0}</td>
                      <td className="num">{fmt(total)} MAD</td>
                      <td>
                        <div className="allocation">
                          <div className="allocation-track">
                            <div
                              className={
                                percent > 90
                                  ? "allocation-fill danger"
                                  : "allocation-fill"
                              }
                              style={{ width: `${percent}%` }}
                            />
                          </div>
                          <span>{Math.round(percent)}%</span>
                          <small>{fmt(allocated)} MAD</small>
                        </div>
                      </td>
                      <td>
                        <span
                          className={`badge ${budget.statut === "clos" ? "neutral" : "success"}`}
                        >
                          {budget.statut === "clos" ? "Clôturé" : "Ouvert"}
                        </span>
                      </td>
                      {canManage && (
                        <td>
                          <div className="budget-actions">
                            {canEdit && <button
                              className="btn-ghost btn-mini"
                              onClick={() => openModal(budget)}
                            >
                              <Pencil size={14} /> Modifier
                            </button>}
                            {budget.statut !== "clos" && canClose && (
                              <button
                                className="btn-ghost btn-mini btn-warn"
                                onClick={() => closeBudget(budget)}
                              >
                                Clôturer
                              </button>
                            )}
                            {canDelete && <button
                              className="btn-ghost btn-mini btn-danger"
                              onClick={() => deleteBudget(budget)}
                            >
                              <Trash2 size={14} /> Supprimer
                            </button>}
                          </div>
                        </td>
                      )}
                    </tr>
                  );
                })
              ) : (
                <tr className="empty-row">
                  <td
                    colSpan={
                      (canViewAllDepartments() ? 8 : 7) + (canManage ? 1 : 0)
                    }
                  >
                    Aucun budget visible.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
      {modal && (
        <div className="modal-overlay active" onMouseDown={closeModal}>
          <div
            className="modal budget-modal"
            role="dialog"
            aria-modal="true"
            onMouseDown={(event) => event.stopPropagation()}
          >
            <div className="modal-header">
              <div>
                <h2>{editing ? "Modifier le budget" : "Nouveau budget"}</h2>
                <p>
                  {editing
                    ? "Les lignes existantes ne peuvent pas dépasser le nouveau montant global."
                    : "Attribuez ce budget à un ou plusieurs départements."}
                </p>
              </div>
              <button
                className="modal-close"
                onClick={closeModal}
                aria-label="Fermer"
              >
                <X size={18} />
              </button>
            </div>
            <form onSubmit={saveBudget}>
              <div className="modal-body">
                <div className="form-grid">
                  <div className="form-field">
                    <label>
                      Code <span className="req">*</span>
                    </label>
                    <input
                      value={form.code}
                      onChange={(event) =>
                        setForm({ ...form, code: event.target.value })
                      }
                      required
                    />
                    {errors.code && (
                      <span className="field-error">{errors.code[0]}</span>
                    )}
                  </div>
                  <div className="form-field">
                    <label>
                      Année <span className="req">*</span>
                    </label>
                    <input
                      type="number"
                      min="2000"
                      value={form.annee}
                      onChange={(event) =>
                        setForm({ ...form, annee: event.target.value })
                      }
                      required
                      disabled={!!editing}
                    />
                  </div>
                  <div className="form-field full">
                    <label>
                      Nom <span className="req">*</span>
                    </label>
                    <input
                      value={form.nom}
                      onChange={(event) =>
                        setForm({ ...form, nom: event.target.value })
                      }
                      required
                    />
                    {errors.nom && (
                      <span className="field-error">{errors.nom[0]}</span>
                    )}
                  </div>
                  <div className="form-field">
                    <label>
                      Montant global (MAD) <span className="req">*</span>
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={form.montant_global}
                      onChange={(event) =>
                        setForm({ ...form, montant_global: event.target.value })
                      }
                      required
                    />
                    {errors.montant_global && (
                      <span className="field-error">
                        {errors.montant_global[0]}
                      </span>
                    )}
                  </div>
                  <div className="form-field">
                    <label>
                      Type <span className="req">*</span>
                    </label>
                    <select
                      value={form.type}
                      onChange={(event) =>
                        setForm({ ...form, type: event.target.value })
                      }
                      disabled={!!editing}
                    >
                      <option value="fonctionnement">Fonctionnement</option>
                      <option value="investissement">Investissement</option>
                    </select>
                  </div>
                  <div className="form-field full">
                    <label>Départements associés</label>
                    <div className="dept-multiselect" ref={deptDropdownRef}>
                      <button
                        type="button"
                        className="dept-multiselect-trigger"
                        onClick={() => setDeptDropdownOpen((open) => !open)}
                      >
                        <span>
                          {form.departement_ids.length
                            ? `${form.departement_ids.length} département(s) sélectionné(s)`
                            : "Sélectionner un ou plusieurs départements"}
                        </span>
                        <ChevronDown size={16} />
                      </button>
                      {deptDropdownOpen && (
                        <div className="dept-multiselect-panel">
                          {departements.map((department) => {
                            const id = String(department.id);
                            const checked =
                              form.departement_ids.includes(id);
                            return (
                              <label
                                className="dept-multiselect-option"
                                key={department.id}
                              >
                                <input
                                  type="checkbox"
                                  checked={checked}
                                  onChange={() =>
                                    setForm((current) => ({
                                      ...current,
                                      departement_ids: checked
                                        ? current.departement_ids.filter(
                                            (value) => value !== id,
                                          )
                                        : [...current.departement_ids, id],
                                    }))
                                  }
                                />
                                {department.nom}
                              </label>
                            );
                          })}
                        </div>
                      )}
                    </div>
                    {form.departement_ids.length > 0 && (
                      <div className="budget-departments dept-selected-chips">
                        {form.departement_ids.map((id) => {
                          const department = departements.find(
                            (d) => String(d.id) === id,
                          );
                          return department ? (
                            <span className="budget-department" key={id}>
                              {department.nom}
                            </span>
                          ) : null;
                        })}
                      </div>
                    )}
                    <span className="field-hint">
                      Les départements sélectionnés pourront voir ce budget et
                      y rattacher des lignes budgétaires.
                    </span>
                  </div>
                  <div className="form-field full">
                    <label>Description</label>
                    <textarea
                      value={form.description}
                      onChange={(event) =>
                        setForm({ ...form, description: event.target.value })
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
                <button
                  type="submit"
                  className="btn-primary"
                  disabled={submitting}
                >
                  {submitting ? "Enregistrement…" : "Enregistrer"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}