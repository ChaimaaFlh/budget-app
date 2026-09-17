import { useCallback, useEffect, useState } from "react";
import { Pencil, Plus, Trash2, X } from "lucide-react";
import api from "../api/axios";
import { useAuth } from "../context/AuthContext";
import { useToast } from "../context/ToastContext";
import "../styles/ArborescenceBudgetaire.css";

// modal peut valoir : "category" (création), "category-edit" (modification), "sub" (création sous-catégorie)
export default function Categories() {
  const { departements, user } = useAuth();
  const { showToast } = useToast();
  const [categories, setCategories] = useState([]);
  const [modal, setModal] = useState(null);
  const [editingId, setEditingId] = useState(null);
  const [nom, setNom] = useState("");
  const [categorieId, setCategorieId] = useState("");
  const [departementId, setDepartementId] = useState("");

  const peutChoisirDepartement = departements.length > 1;

  const load = useCallback(
    () =>
      api
        .get("/categories")
        .then(({ data }) => setCategories(data))
        .catch(() => showToast("Impossible de charger les catégories.", true)),
    [showToast],
  );
  useEffect(() => { load(); }, [load]);

  const openCreateCategory = () => {
    setNom("");
    setDepartementId(String(departements[0]?.id || user?.departement_id || ""));
    setEditingId(null);
    setModal("category");
  };

  const openEditCategory = (categorie) => {
    setNom(categorie.nom);
    setDepartementId(String(categorie.departement_id || ""));
    setEditingId(categorie.id);
    setModal("category-edit");
  };

  const openCreateSub = () => {
    setNom("");
    setCategorieId(String(categories[0]?.id || ""));
    setModal("sub");
  };

  const save = async (e) => {
    e.preventDefault();
    try {
      if (modal === "category") {
        await api.post("/categories", { nom, departement_id: Number(departementId) });
      } else if (modal === "category-edit") {
        await api.put(`/categories/${editingId}`, { nom, departement_id: Number(departementId) });
      } else {
        await api.post("/sous-categories", { nom, categorie_id: Number(categorieId) });
      }
      setModal(null);
      setNom("");
      setEditingId(null);
      load();
      showToast("Enregistré.");
    } catch (e) {
      showToast(e.response?.data?.message || "Enregistrement impossible.", true);
    }
  };

  const remove = async (url) => {
    if (!window.confirm("Supprimer cet élément ?")) return;
    try {
      await api.delete(url);
      load();
    } catch (e) {
      showToast(e.response?.data?.message || "Suppression impossible.", true);
    }
  };

  return (
    <div className="tree-page">
      <div className="tree-page-head">
        <div><h1>Catégories</h1><p>Administration du référentiel budgétaire.</p></div>
        <div className="tree-head-actions">
          <button className="btn-primary" onClick={openCreateCategory}><Plus size={15}/> Catégorie</button>
          <button className="btn-primary" onClick={openCreateSub}><Plus size={15}/> Sous-catégorie</button>
        </div>
      </div>
      <section className="panel">
        <div className="table-wrap">
          <table>
            <thead><tr><th>Catégorie</th><th>Département</th><th>Sous-catégories</th><th/></tr></thead>
            <tbody>
              {categories.map(c => (
                <tr key={c.id}>
                  <td><strong>{c.nom}</strong></td>
                  <td>{c.departement?.nom || "—"}</td>
                  <td>
                    {(c.sous_categories || []).map(s => (
                      <span className="badge neutral" key={s.id}>
                        {s.nom}{" "}
                        <button className="btn-ghost btn-mini btn-danger" onClick={() => remove(`/sous-categories/${s.id}`)}><X size={12}/></button>
                      </span>
                    ))}
                  </td>
                  <td>
                    <div className="category-row-actions">
                      <button className="btn-ghost btn-mini" onClick={() => openEditCategory(c)}><Pencil size={14}/> Modifier</button>
                      <button className="btn-ghost btn-mini btn-danger" onClick={() => remove(`/categories/${c.id}`)}><Trash2 size={14}/> Supprimer</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
      {modal && (
        <div className="modal-overlay active">
          <div className="modal">
            <div className="modal-header">
              <h2>
                {modal === "category" && "Nouvelle catégorie"}
                {modal === "category-edit" && "Modifier la catégorie"}
                {modal === "sub" && "Nouvelle sous-catégorie"}
              </h2>
              <button className="modal-close" onClick={() => setModal(null)}><X/></button>
            </div>
            <form onSubmit={save}>
              <div className="modal-body">
                <div className="form-grid">
                  {modal === "sub" && (
                    <div className="form-field full">
                      <label>Catégorie</label>
                      <select value={categorieId} onChange={e => setCategorieId(e.target.value)} required>
                        {categories.map(c => <option value={c.id} key={c.id}>{c.nom}</option>)}
                      </select>
                    </div>
                  )}
                  {(modal === "category" || modal === "category-edit") && (
                    <div className="form-field full">
                      <label>Département</label>
                      <select
                        value={departementId}
                        onChange={e => setDepartementId(e.target.value)}
                        disabled={!peutChoisirDepartement}
                        required
                      >
                        {departements.map(d => <option value={d.id} key={d.id}>{d.nom}</option>)}
                      </select>
                    </div>
                  )}
                  <div className="form-field full">
                    <label>Nom</label>
                    <input value={nom} onChange={e => setNom(e.target.value)} required autoFocus/>
                  </div>
                </div>
              </div>
              <div className="modal-footer">
                <button className="btn-primary">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}