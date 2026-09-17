import { useCallback, useEffect, useState } from "react";
import { Pencil, Plus, Trash2, X } from "lucide-react";
import api from "../api/axios";
import { useToast } from "../context/ToastContext";
import { getPageCache, setPageCache } from "../lib/pageCache";
import "../styles/Departements.css";

const CACHE_KEY = "departements";

export default function Departements() {
  const { showToast } = useToast();
  const cached = getPageCache(CACHE_KEY);
  const [departements, setDepartements] = useState(cached ?? []);
  const [loading, setLoading] = useState(!cached);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [nom, setNom] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const loadDepartments = useCallback(async () => {
    try {
      const { data } = await api.get("/departements");
      setDepartements(data);
      setPageCache(CACHE_KEY, data);
    } catch (requestError) {
      showToast(
        requestError.response?.data?.message ||
          "Impossible de charger les départements.",
        true,
      );
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    loadDepartments();
  }, [loadDepartments]);

  const openModal = (department = null) => {
    setEditing(department);
    setNom(department?.nom || "");
    setError("");
    setIsModalOpen(true);
  };
  const closeModal = () => {
    if (!submitting) setIsModalOpen(false);
  };

  const saveDepartment = async (event) => {
    event.preventDefault();
    setSubmitting(true);
    setError("");
    try {
      if (editing) await api.put(`/departements/${editing.id}`, { nom });
      else await api.post("/departements", { nom });
      showToast(editing ? "Département modifié." : "Département créé.");
      setIsModalOpen(false);
      await loadDepartments();
    } catch (requestError) {
      setError(
        requestError.response?.data?.errors?.nom?.[0] ||
          requestError.response?.data?.message ||
          "Enregistrement impossible.",
      );
    } finally {
      setSubmitting(false);
    }
  };

  const deleteDepartment = async (department) => {
    if (!window.confirm(`Supprimer le département « ${department.nom} » ?`))
      return;
    try {
      await api.delete(`/departements/${department.id}`);
      showToast("Département supprimé.");
      await loadDepartments();
    } catch (requestError) {
      showToast(
        requestError.response?.data?.message || "Suppression impossible.",
        true,
      );
    }
  };

  if (loading)
    return (
      <div className="departments-loading">Chargement des départements…</div>
    );

  return (
    <div className="departments-page">
      <div className="departments-page-head">
        <div>
          <div className="eyebrow">Administration</div>
          <h1>Départements</h1>
          <p>
            Gérez les entités auxquelles les utilisateurs et les lignes
            budgétaires sont rattachés.
          </p>
        </div>
        <button className="btn-primary" onClick={() => openModal()}>
          <Plus size={16} /> Nouveau département
        </button>
      </div>
      <section className="panel departments-panel">
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Nom</th>
                <th>Date de création</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {departements.length ? (
                departements.map((department) => (
                  <tr key={department.id}>
                    <td>
                      <strong>{department.nom}</strong>
                    </td>
                    <td>
                      {department.created_at
                        ? new Date(department.created_at).toLocaleDateString(
                            "fr-FR",
                          )
                        : "—"}
                    </td>
                    <td>
                      <div className="department-actions">
                        <button
                          className="btn-ghost btn-mini"
                          onClick={() => openModal(department)}
                        >
                          <Pencil size={14} /> Modifier
                        </button>
                        <button
                          className="btn-ghost btn-mini btn-danger"
                          onClick={() => deleteDepartment(department)}
                        >
                          <Trash2 size={14} /> Supprimer
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              ) : (
                <tr className="empty-row">
                  <td colSpan={3}>Aucun département.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
      {isModalOpen && (
        <div className="modal-overlay active" onMouseDown={closeModal}>
          <div
            className="modal department-modal"
            role="dialog"
            aria-modal="true"
            onMouseDown={(event) => event.stopPropagation()}
          >
            <div className="modal-header">
              <div>
                <h2>
                  {editing ? "Modifier le département" : "Nouveau département"}
                </h2>
                <p>Indiquez son intitulé complet.</p>
              </div>
              <button
                className="modal-close"
                onClick={closeModal}
                aria-label="Fermer"
              >
                <X size={18} />
              </button>
            </div>
            <form onSubmit={saveDepartment}>
              <div className="modal-body">
                <div className="form-field">
                  <label>
                    Nom <span className="req">*</span>
                  </label>
                  <input
                    value={nom}
                    onChange={(event) => setNom(event.target.value)}
                    required
                    autoFocus
                  />
                  {error && <span className="field-error">{error}</span>}
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