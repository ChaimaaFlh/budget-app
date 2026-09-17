import { useCallback, useEffect, useState } from "react";
import { List, Pencil, Plus, Trash2, X } from "lucide-react";
import api from "../api/axios";
import { useAuth } from "../context/AuthContext";
import { useToast } from "../context/ToastContext";
import "../styles/Consommation.css";

const emptyForm = {
  nom: "",
  email: "",
  telephone: "",
  adresse: "",
  score: "",
  score_annee: String(new Date().getFullYear()),
};

export default function Fournisseurs() {
  const { hasPermission } = useAuth();
  const { showToast } = useToast();
  const [list, setList] = useState([]);
  const [modal, setModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [details, setDetails] = useState(null);

  const load = useCallback(
    () =>
      api
        .get("/fournisseurs")
        .then((response) => setList(response.data))
        .catch(() => showToast("Impossible de charger les fournisseurs.", true)),
    [showToast],
  );
  useEffect(() => { load(); }, [load]);

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm);
    setModal(true);
  };
  const openEdit = (fournisseur) => {
    setEditing(fournisseur);
    setForm({
      nom: fournisseur.nom || "",
      email: fournisseur.email || "",
      telephone: fournisseur.telephone || "",
      adresse: fournisseur.adresse || "",
      score: fournisseur.score ?? "",
      score_annee: fournisseur.score_annee || String(new Date().getFullYear()),
    });
    setModal(true);
  };
  const submit = async (event) => {
    event.preventDefault();
    const payload = {
      ...form,
      score: form.score === "" ? null : Number(form.score),
      score_annee: form.score_annee === "" ? null : Number(form.score_annee),
    };
    try {
      if (editing) await api.put(`/fournisseurs/${editing.id}`, payload);
      else await api.post("/fournisseurs", payload);
      showToast(editing ? "Fournisseur mis à jour." : "Fournisseur créé.");
      setModal(false);
      await load();
    } catch (error) {
      showToast(error.response?.data?.message || "Enregistrement impossible.", true);
    }
  };
  const remove = async (fournisseur) => {
    if (!window.confirm(`Supprimer le fournisseur « ${fournisseur.nom} » ?`)) return;
    try {
      await api.delete(`/fournisseurs/${fournisseur.id}`);
      showToast("Fournisseur supprimé.");
      await load();
    } catch (error) {
      showToast(error.response?.data?.message || "Suppression impossible.", true);
    }
  };
  const openDetails = async (fournisseur) => {
    try {
      const { data } = await api.get(`/fournisseurs/${fournisseur.id}`);
      setDetails(data);
    } catch (error) {
      showToast(error.response?.data?.message || "Impossible de charger l'historique.", true);
    }
  };

  return (
    <div className="consumption-page">
      <div className="consumption-head">
        <div>
          <h1>Fournisseurs</h1>
          <p>Référentiel des partenaires et évaluation annuelle AGMA.</p>
        </div>
        {hasPermission("fournisseur.create") && (
          <button className="btn-primary" onClick={openCreate}>
            <Plus size={16} /> Nouveau fournisseur
          </button>
        )}
      </div>
      <section className="panel">
        <div className="table-wrap">
          <table>
            <thead><tr><th>Fournisseur</th><th>Contact</th><th>Bons</th><th>Score moyen (5 ans)</th><th /></tr></thead>
            <tbody>
              {list.map((fournisseur) => (
                <tr key={fournisseur.id}>
                  <td><strong>{fournisseur.nom}</strong><span className="table-subtitle">{fournisseur.adresse || "—"}</span></td>
                  <td>{fournisseur.email || fournisseur.telephone || "—"}</td>
                  <td>{fournisseur.bons_commande_count || 0}</td>
                  <td>{fournisseur.score == null ? "Non évalué" : <span className={`badge ${Number(fournisseur.score) >= 80 ? "success" : "warning"}`}>{Number(fournisseur.score).toFixed(2)}/100</span>}</td>
                  <td>
                    <button className="btn-ghost btn-mini" onClick={() => openDetails(fournisseur)}><List size={14} /> Détail</button>
                    {hasPermission("fournisseur.edit") && <button className="btn-ghost btn-mini" onClick={() => openEdit(fournisseur)}><Pencil size={14} /> Modifier</button>}
                    {hasPermission("fournisseur.delete") && <button className="btn-ghost btn-mini btn-danger" onClick={() => remove(fournisseur)}><Trash2 size={14} /> Supprimer</button>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
      {modal && <div className="modal-overlay active" onMouseDown={() => setModal(false)}><div className="modal" role="dialog" aria-modal="true" onMouseDown={(event) => event.stopPropagation()}><div className="modal-header"><h2>{editing ? "Modifier le fournisseur" : "Nouveau fournisseur"}</h2><button className="modal-close" onClick={() => setModal(false)}><X /></button></div><form onSubmit={submit}><div className="modal-body"><div className="form-grid">{Object.entries({ nom: "Nom *", email: "E-mail", telephone: "Téléphone", adresse: "Adresse", score: "Score annuel (sur 100)", score_annee: "Année d'évaluation" }).map(([key, label]) => <div className="form-field" key={key}><label>{label}</label><input required={key === "nom"} type={key === "email" ? "email" : key === "score" || key === "score_annee" ? "number" : "text"} min={key === "score" ? "0" : key === "score_annee" ? "2000" : undefined} max={key === "score" ? "100" : key === "score_annee" ? "2200" : undefined} value={form[key]} onChange={(event) => setForm({ ...form, [key]: event.target.value })} /></div>)}</div></div><div className="modal-footer"><button type="button" className="btn-ghost" onClick={() => setModal(false)}>Annuler</button><button className="btn-primary">Enregistrer</button></div></form></div></div>}
      {details && <div className="modal-overlay active" onMouseDown={() => setDetails(null)}><div className="modal" role="dialog" aria-modal="true" onMouseDown={(event) => event.stopPropagation()}><div className="modal-header"><div><h2>Historique des notes</h2><p>{details.fournisseur.nom} · moyenne des 5 dernières années : {details.score_moyen == null ? "Non évalué" : `${Number(details.score_moyen).toFixed(2)}/100`}</p></div><button className="modal-close" onClick={() => setDetails(null)}><X /></button></div><div className="modal-body"><div className="table-wrap"><table><thead><tr><th>Année</th><th className="num">Note</th></tr></thead><tbody>{details.scores.length ? details.scores.map((score) => <tr key={score.id}><td>{score.annee}</td><td className="num">{score.score}/100</td></tr>) : <tr className="empty-row"><td colSpan={2}>Aucune note sur les 5 dernières années.</td></tr>}</tbody></table></div></div><div className="modal-footer"><button className="btn-primary" onClick={() => setDetails(null)}>Fermer</button></div></div></div>}
    </div>
  );
}
