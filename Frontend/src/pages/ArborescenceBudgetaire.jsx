import { useCallback, useEffect, useMemo, useState } from "react";
import { ChevronRight, Grid2X2, Plus, X } from "lucide-react";
import { useNavigate } from "react-router-dom";
import api from "../api/axios";
import { useAuth } from "../context/AuthContext";
import { useToast } from "../context/ToastContext";
import { getPageCache, setPageCache } from "../lib/pageCache";
import "../styles/ArborescenceBudgetaire.css";

const CACHE_KEY = "arborescence";

const fmt = (value) =>
  new Intl.NumberFormat("fr-MA", { maximumFractionDigits: 0 }).format(
    Number(value || 0),
  );

export default function ArborescenceBudgetaire({ readOnly = false }) {
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const { showToast } = useToast();
  const cached = getPageCache(CACHE_KEY);
  const [categories, setCategories] = useState(cached?.categories ?? []);
  const [lignes, setLignes] = useState(cached?.lignes ?? []);
  const [loading, setLoading] = useState(!cached);
  const [modal, setModal] = useState(null);
  const [nom, setNom] = useState("");
  const [categorieId, setCategorieId] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState({});

  const loadData = useCallback(async () => {
    try {
      const [{ data: categoriesData }, { data: lignesData }] =
        await Promise.all([api.get("/categories"), api.get("/ligne-budgets")]);
      setCategories(categoriesData);
      setLignes(lignesData);
      setPageCache(CACHE_KEY, { categories: categoriesData, lignes: lignesData });
    } catch {
      showToast("Impossible de charger l'arborescence budgétaire.", true);
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const lignesParSousCategorie = useMemo(() => {
    const map = new Map();
    lignes.forEach((ligne) => {
      if (!ligne.sous_categorie_id) return;
      const current = map.get(ligne.sous_categorie_id) || [];
      current.push(ligne);
      map.set(ligne.sous_categorie_id, current);
    });
    return map;
  }, [lignes]);

  // Lignes rattachées directement à une catégorie, sans passer par une sous-catégorie
  const lignesParCategorie = useMemo(() => {
    const map = new Map();
    lignes.forEach((ligne) => {
      if (ligne.sous_categorie_id) return;
      const catId = ligne.categorie_id;
      if (!catId) return;
      const current = map.get(catId) || [];
      current.push(ligne);
      map.set(catId, current);
    });
    return map;
  }, [lignes]);

  // Les catégories et sous-catégories sont un référentiel global : elles
  // doivent rester visibles même lorsqu'aucune ligne n'y est encore rattachée.
  const categoriesVisibles = categories;

  const openModal = (type) => {
    setModal(type);
    setNom("");
    setCategorieId(
      type === "sub" && categories[0] ? String(categories[0].id) : "",
    );
    setErrors({});
  };

  const closeModal = () => {
    if (!submitting) setModal(null);
  };

  const submit = async (event) => {
    event.preventDefault();
    setSubmitting(true);
    setErrors({});
    try {
      if (modal === "category") {
        await api.post("/categories", { nom });
      } else {
        await api.post("/sous-categories", {
          nom,
          categorie_id: Number(categorieId),
        });
      }
      showToast(
        modal === "category" ? "Catégorie créée." : "Sous-catégorie créée.",
      );
      setModal(null);
      await loadData();
    } catch (error) {
      if (error.response?.status === 422)
        setErrors(error.response.data.errors || {});
      else
        showToast(
          error.response?.data?.message || "L'enregistrement a échoué.",
          true,
        );
    } finally {
      setSubmitting(false);
    }
  };

  const remove = async (resource, entity, label, event) => {
    event.preventDefault();
    event.stopPropagation();
    if (!window.confirm(`Supprimer ${label} « ${entity.nom} » ?`)) return;
    try {
      await api.delete(`/${resource}/${entity.id}`);
      showToast(`${label} supprimée.`);
      await loadData();
    } catch (error) {
      showToast(
        error.response?.data?.message || "Suppression impossible.",
        true,
      );
    }
  };

  if (loading)
    return <div className="tree-loading">Chargement de l’arborescence…</div>;

  return (
    <div className="tree-page">
      <div className="tree-page-head">
        <div>
          <h1>Arborescence budgétaire</h1>
          <p>Référentiel de votre département</p>
        </div>
        {!readOnly && (hasPermission("categorie.create") || hasPermission("souscategorie.create")) && (
          <div className="tree-head-actions">
            <button
              className="btn-primary"
              disabled={!hasPermission("categorie.create")}
              onClick={() => openModal("category")}
            >
              <Plus size={15} /> Catégorie
            </button>
            <button
              className="btn-primary"
              disabled={!hasPermission("souscategorie.create")}
              onClick={() => openModal("sub")}
            >
              <Plus size={15} /> Sous-catégorie
            </button>
          </div>
        )}
      </div>

      <div className="tree">
        {categoriesVisibles.map((category) => {
          const sousCategories = category.sous_categories || [];
          const lignesDirectes = lignesParCategorie.get(category.id) || [];
          const subStats = sousCategories.map((sub) => {
            const subLignes = lignesParSousCategorie.get(sub.id) || [];
            const budget = subLignes.reduce(
              (sum, ligne) => sum + Number(ligne.montant_alloue || 0),
              0,
            );
            const consomme = subLignes.reduce(
              (sum, ligne) => sum + Number(ligne.total_consomme || 0),
              0,
            );
            return { sub, subLignes, budget, consomme };
          });

          const totalBudget =
            lignesDirectes.reduce(
              (sum, ligne) => sum + Number(ligne.montant_alloue || 0),
              0,
            ) + subStats.reduce((sum, s) => sum + s.budget, 0);
          const totalConsomme =
            lignesDirectes.reduce(
              (sum, ligne) => sum + Number(ligne.total_consomme || 0),
              0,
            ) + subStats.reduce((sum, s) => sum + s.consomme, 0);
          const percent =
            totalBudget > 0 ? (totalConsomme / totalBudget) * 100 : 0;
          const progressClass =
            percent >= 100 ? "danger" : percent >= 80 ? "warning" : "success";

          return (
            <details className="cat-node" key={category.id} open>
              <summary>
                <div className="cat-summary-main">
                  <ChevronRight className="chevron" size={16} />
                  <Grid2X2 size={16} />
                  <span className="cat-name">{category.nom}</span>
                  <span className="cat-count">
                    {subStats.length} sous-catégorie(s)
                  </span>
                </div>
                <div className="cat-summary-figures">
                  <div className="tree-figure">
                    <div className="v">{fmt(totalBudget)}</div>
                    <div className="l">Budget</div>
                  </div>
                  <div className="tree-figure">
                    <div className="v">{fmt(totalConsomme)}</div>
                    <div className="l">Consommé</div>
                  </div>
                  <div className="tree-mini-track">
                    <div
                      className={`tree-mini-fill ${progressClass}`}
                      style={{ width: `${Math.min(100, percent)}%` }}
                    />
                  </div>
                  {!readOnly && hasPermission("categorie.delete") && (
                    <button
                      type="button"
                      aria-label={`Supprimer la catégorie ${category.nom}`}
                      title="Supprimer la catégorie (uniquement si elle est vide)"
                      className="btn-ghost btn-mini btn-danger delete-tree-button"
                      onClick={(event) =>
                        remove("categories", category, "la catégorie", event)
                      }
                    >
                      <X size={14} />
                    </button>
                  )}
                </div>
              </summary>
              <div className="cat-body">
                {lignesDirectes.length > 0 && (
                  <div className="ligne-list ligne-list-directe">
                    <p className="direct-lines-label">
                      Lignes rattachées directement à la catégorie
                    </p>
                    {lignesDirectes.map((ligne) => (
                      <LigneItem
                        key={ligne.id}
                        ligne={ligne}
                        fmt={fmt}
                        navigate={navigate}
                      />
                    ))}
                  </div>
                )}
                {subStats.length ? (
                  subStats.map(({ sub, subLignes }) => (
                    <details className="subcat-node" key={sub.id}>
                      <summary>
                        <span className="subcat-name">{sub.nom}</span>
                        <span className="subcat-count">
                          {subLignes.length} ligne(s) visible(s)
                        </span>
                        {!readOnly && hasPermission("souscategorie.delete") && (
                          <button
                            type="button"
                            aria-label={`Supprimer la sous-catégorie ${sub.nom}`}
                            title="Supprimer la sous-catégorie (uniquement si elle est vide)"
                            className="btn-ghost btn-mini btn-danger delete-tree-button"
                            onClick={(event) =>
                              remove(
                                "sous-categories",
                                sub,
                                "la sous-catégorie",
                                event,
                              )
                            }
                          >
                            <X size={14} />
                          </button>
                        )}
                      </summary>
                      <div className="ligne-list">
                        {subLignes.length ? (
                          subLignes.map((ligne) => (
                            <LigneItem
                              key={ligne.id}
                              ligne={ligne}
                              fmt={fmt}
                              navigate={navigate}
                            />
                          ))
                        ) : (
                          <div className="no-lignes">Aucune ligne visible.</div>
                        )}
                      </div>
                    </details>
                  ))
                ) : lignesDirectes.length === 0 ? (
                  <p className="no-sous-categories">Aucune sous-catégorie.</p>
                ) : null}
              </div>
            </details>
          );
        })}
        {!categoriesVisibles.length && (
          <p className="no-sous-categories">
            Aucune catégorie ou ligne budgétaire visible dans votre département.
          </p>
        )}
      </div>

      {modal && (
        <div className="modal-overlay active" onMouseDown={closeModal}>
          <div
            className="modal"
            role="dialog"
            aria-modal="true"
            onMouseDown={(event) => event.stopPropagation()}
          >
            <div className="modal-header">
              <div>
                <h2>
                  Nouvelle{" "}
                  {modal === "category" ? "catégorie" : "sous-catégorie"}
                </h2>
                <p>Complétez les informations du référentiel.</p>
              </div>
              <button className="modal-close" onClick={closeModal}>
                <X size={18} />
              </button>
            </div>
            <form onSubmit={submit}>
              <div className="modal-body">
                <div className="form-grid">
                  {modal === "sub" && (
                    <div className="form-field full">
                      <label>
                        Catégorie <span className="req">*</span>
                      </label>
                      <select
                        value={categorieId}
                        onChange={(event) => setCategorieId(event.target.value)}
                        required
                      >
                        <option value="">Sélectionner une catégorie</option>
                        {categories.map((category) => (
                          <option key={category.id} value={category.id}>
                            {category.nom}
                          </option>
                        ))}
                      </select>
                      {errors.categorie_id && (
                        <span className="field-error">
                          {errors.categorie_id[0]}
                        </span>
                      )}
                    </div>
                  )}
                  <div className="form-field full">
                    <label>
                      Nom <span className="req">*</span>
                    </label>
                    <input
                      value={nom}
                      onChange={(event) => setNom(event.target.value)}
                      required
                      autoFocus
                    />
                    {errors.nom && (
                      <span className="field-error">{errors.nom[0]}</span>
                    )}
                  </div>
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

function LigneItem({ ligne, fmt, navigate }) {
  const status = ligne.statut || "Disponible";
  const statusClass =
    status === "Dépassement"
      ? "danger"
      : status === "Totalement consommé" || status === "Totalement Consommé"
        ? "warning"
        : "success";
  return (
    <div className="ligne-item">
      <div className="li-main">
        <div className="li-code">{ligne.code}</div>
        <div className="li-name">{ligne.intitule}</div>
        <div className="li-meta">
          {fmt(ligne.montant_alloue)} MAD · {fmt(ligne.total_consomme)} MAD
          consommé (BC)
        </div>
      </div>
      <div className="li-figures">
        <span
          className={`badge ${ligne.budget?.type === "investissement" ? "investissement" : "fonctionnement"}`}
        >
          {ligne.budget?.type || "—"}
        </span>
        <span className={`badge ${statusClass}`}>{status}</span>
        <button
          className="btn-ghost btn-mini"
          onClick={() => navigate(`/lignes/${ligne.id}`)}
        >
          Détail
        </button>
      </div>
    </div>
  );
}