import { useCallback, useEffect, useState } from "react";
import { ChevronRight, KeyRound, Pencil, Plus, Power, ShieldCheck, X } from "lucide-react";
import api from "../api/axios";
import { useAuth } from "../context/AuthContext";
import { useToast } from "../context/ToastContext";
import { getPageCache, setPageCache } from "../lib/pageCache";
import "../styles/Utilisateurs.css";

const CACHE_KEY = "utilisateurs";

const emptyForm = {
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  departement_id: "",
};

const permissionGroups = {
  budget: "Budgets",
  ligne: "Lignes budgétaires",
  bc: "Bons de commande",
  facture: "Factures",
  fournisseur: "Fournisseurs",
  categorie: "Catégories",
  souscategorie: "Sous-catégories",
  departement: "Départements",
  user: "Utilisateurs",
};

export default function Utilisateurs() {
  const { user: currentUser, departements } = useAuth();
  const { showToast } = useToast();
  const cached = getPageCache(CACHE_KEY);
  const [users, setUsers] = useState(cached?.users ?? []);
  const [permissions, setPermissions] = useState(cached?.permissions ?? []);
  const [packs, setPacks] = useState(cached?.packs ?? []);
  const [loading, setLoading] = useState(!cached);
  const [modal, setModal] = useState(null);
  const [selectedUser, setSelectedUser] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [editForm, setEditForm] = useState({ name: "", email: "", departement_id: "" });
  const [selectedPermissionIds, setSelectedPermissionIds] = useState([]);
  const [openPermissionGroups, setOpenPermissionGroups] = useState({});
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  const loadData = useCallback(async () => {
    try {
      const [{ data: usersData }, { data: permissionsData }, { data: packsData }] =
        await Promise.all([api.get("/users"), api.get("/permissions"), api.get("/permission-packs")]);
      setUsers(usersData);
      setPermissions(permissionsData);
      setPacks(packsData);
      setPageCache(CACHE_KEY, {
        users: usersData,
        permissions: permissionsData,
        packs: packsData,
      });
    } catch (error) {
      showToast(
        error.response?.data?.message ||
          "Impossible de charger les utilisateurs.",
        true,
      );
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const closeModal = () => {
    if (!submitting) {
      setModal(null);
      setSelectedUser(null);
      setErrors({});
    }
  };

  const openCreateModal = () => {
    setForm({
      ...emptyForm,
      departement_id: departements[0] ? String(departements[0].id) : "",
    });
    setErrors({});
    setModal("create");
  };

  const openEditModal = (targetUser) => {
    setSelectedUser(targetUser);
    setEditForm({
      name: targetUser.name,
      email: targetUser.email,
      departement_id: String(targetUser.departement_id || ""),
    });
    setErrors({});
    setModal("edit");
  };

  const openPermissionsModal = (targetUser) => {
    setSelectedUser(targetUser);
    setSelectedPermissionIds(
      (targetUser.permissions || []).map((permission) => permission.id),
    );
    setOpenPermissionGroups({});
    setErrors({});
    setModal("permissions");
  };

  const createUser = async (event) => {
    event.preventDefault();
    setSubmitting(true);
    setErrors({});
    try {
      await api.post("/users", {
        ...form,
        departement_id: Number(form.departement_id),
      });
      showToast(
        "Compte créé. L’utilisateur devra modifier son mot de passe à sa première connexion.",
      );
      closeModal();
      await loadData();
    } catch (error) {
      if (error.response?.status === 422)
        setErrors(error.response.data.errors || {});
      else
        showToast(
          error.response?.data?.message || "Création du compte impossible.",
          true,
        );
    } finally {
      setSubmitting(false);
    }
  };

  const updateUser = async (event) => {
    event.preventDefault();
    if (!selectedUser) return;
    setSubmitting(true);
    setErrors({});
    try {
      await api.put(`/users/${selectedUser.id}`, {
        ...editForm,
        departement_id: Number(editForm.departement_id),
      });
      showToast("Utilisateur mis à jour.");
      closeModal();
      await loadData();
    } catch (error) {
      if (error.response?.status === 422)
        setErrors(error.response.data.errors || {});
      else
        showToast(
          error.response?.data?.message || "Mise à jour impossible.",
          true,
        );
    } finally {
      setSubmitting(false);
    }
  };

  const savePermissions = async () => {
    if (!selectedUser) return;
    setSubmitting(true);
    try {
      await api.put(`/users/${selectedUser.id}/permissions`, {
        permission_ids: selectedPermissionIds,
      });
      showToast("Permissions mises à jour.");
      closeModal();
      await loadData();
    } catch (error) {
      setErrors(error.response?.data?.errors || {});
      showToast(
        error.response?.data?.message ||
          "Mise à jour des permissions impossible.",
        true,
      );
    } finally {
      setSubmitting(false);
    }
  };

  const toggleStatus = async (targetUser) => {
    const nextStatus = !targetUser.is_active;
    const action = nextStatus ? "réactiver" : "désactiver";
    if (
      !window.confirm(`Voulez-vous ${action} le compte de ${targetUser.name} ?`)
    )
      return;
    try {
      await api.patch(`/users/${targetUser.id}/status`, {
        is_active: nextStatus,
      });
      showToast(`Compte ${nextStatus ? "réactivé" : "désactivé"}.`);
      await loadData();
    } catch (error) {
      showToast(
        error.response?.data?.message || "Modification du statut impossible.",
        true,
      );
    }
  };

  const togglePermission = (permissionId) => {
    setSelectedPermissionIds((current) =>
      current.includes(permissionId)
        ? current.filter((id) => id !== permissionId)
        : [...current, permissionId],
    );
  };
  const applyPack = (packId) => {
    const pack = packs.find((item) => String(item.id) === packId);
    if (pack) setSelectedPermissionIds(pack.permissions.map((permission) => permission.id));
  };
  const togglePermissionGroup = (items) => {
    const ids = items.map((permission) => permission.id);
    const allSelected = ids.every((id) => selectedPermissionIds.includes(id));
    setSelectedPermissionIds((current) =>
      allSelected
        ? current.filter((id) => !ids.includes(id))
        : [...new Set([...current, ...ids])],
    );
  };
  const togglePermissionGroupOpen = (group) => {
    setOpenPermissionGroups((current) => ({
      ...current,
      [group]: !current[group],
    }));
  };
  const groupedPermissions = permissions.reduce((groups, permission) => {
    const group = permission.code?.split(".")[0] || "other";
    return { ...groups, [group]: [...(groups[group] || []), permission] };
  }, {});

  if (loading)
    return <div className="users-loading">Chargement des utilisateurs…</div>;

  return (
    <div className="users-page">
      <div className="users-page-head">
        <div>
          <div className="eyebrow">Administration</div>
          <h1>Utilisateurs</h1>
          <p>Créez les comptes, gérez leurs accès et contrôlez leur statut.</p>
        </div>
        <button className="btn-primary" onClick={openCreateModal}>
          <Plus size={16} /> Nouvel utilisateur
        </button>
      </div>

      <section className="panel users-panel">
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Utilisateur</th>
                <th>Département</th>
                <th>Permissions</th>
                <th>Statut</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {users.length ? (
                users.map((targetUser) => (
                  <tr key={targetUser.id}>
                    <td>
                      <div className="user-cell">
                        <span className="user-avatar">
                          {targetUser.name?.[0]?.toUpperCase() || "?"}
                        </span>
                        <div>
                          <strong>{targetUser.name}</strong>
                          <span>{targetUser.email}</span>
                        </div>
                      </div>
                    </td>
                    <td>{targetUser.departement?.nom || "—"}</td>
                    <td>
                      <span className="permissions-count">
                        <KeyRound size={14} />{" "}
                        {(targetUser.permissions || []).length} permission(s)
                      </span>
                    </td>
                    <td>
                      <span
                        className={`badge ${targetUser.is_active ? "success" : "neutral"}`}
                      >
                        {targetUser.is_active ? "Actif" : "Désactivé"}
                      </span>
                    </td>
                    <td>
                      <div className="user-actions">
                        <button
                          className="btn-ghost btn-mini"
                          onClick={() => openEditModal(targetUser)}
                        >
                          <Pencil size={14} /> Modifier
                        </button>
                        <button
                          className="btn-ghost btn-mini"
                          onClick={() => openPermissionsModal(targetUser)}
                          disabled={targetUser.id === currentUser?.id}
                          title={
                            targetUser.id === currentUser?.id
                              ? "Vous ne pouvez pas modifier vos propres permissions."
                              : undefined
                          }
                        >
                          <ShieldCheck size={14} /> Permissions
                        </button>
                        <button
                          className={`btn-ghost btn-mini ${targetUser.is_active ? "btn-warn" : ""}`}
                          onClick={() => toggleStatus(targetUser)}
                          disabled={targetUser.id === currentUser?.id}
                        >
                          <Power size={14} />{" "}
                          {targetUser.is_active ? "Désactiver" : "Réactiver"}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              ) : (
                <tr className="empty-row">
                  <td colSpan={5}>Aucun utilisateur.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      {modal && (
        <div className="modal-overlay active" onMouseDown={closeModal}>
          <div
            className="modal users-modal"
            role="dialog"
            aria-modal="true"
            onMouseDown={(event) => event.stopPropagation()}
          >
            <div className="modal-header">
              <div>
                <h2>
                  {modal === "create" && "Nouvel utilisateur"}
                  {modal === "edit" && `Modifier ${selectedUser?.name}`}
                  {modal === "permissions" && `Permissions de ${selectedUser?.name}`}
                </h2>
                <p>
                  {modal === "create" &&
                    "Le mot de passe devra être modifié à la première connexion."}
                  {modal === "edit" &&
                    "Modifiez les informations du compte."}
                  {modal === "permissions" &&
                    "Sélectionnez les accès autorisés pour ce compte."}
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

            {modal === "create" ? (
              <form onSubmit={createUser}>
                <div className="modal-body">
                  <div className="form-grid">
                    <div className="form-field">
                      <label>
                        Nom complet <span className="req">*</span>
                      </label>
                      <input
                        value={form.name}
                        onChange={(event) =>
                          setForm({ ...form, name: event.target.value })
                        }
                        required
                        autoFocus
                      />
                      {errors.name && (
                        <span className="field-error">{errors.name[0]}</span>
                      )}
                    </div>
                    <div className="form-field">
                      <label>
                        E-mail <span className="req">*</span>
                      </label>
                      <input
                        type="email"
                        value={form.email}
                        onChange={(event) =>
                          setForm({ ...form, email: event.target.value })
                        }
                        required
                      />
                      {errors.email && (
                        <span className="field-error">{errors.email[0]}</span>
                      )}
                    </div>
                    <div className="form-field full">
                      <label>
                        Département <span className="req">*</span>
                      </label>
                      <select
                        value={form.departement_id}
                        onChange={(event) =>
                          setForm({
                            ...form,
                            departement_id: event.target.value,
                          })
                        }
                        required
                      >
                        <option value="">Sélectionner un département</option>
                        {departements.map((department) => (
                          <option key={department.id} value={department.id}>
                            {department.nom}
                          </option>
                        ))}
                      </select>
                      {errors.departement_id && (
                        <span className="field-error">
                          {errors.departement_id[0]}
                        </span>
                      )}
                    </div>
                    <div className="form-field">
                      <label>
                        Mot de passe <span className="req">*</span>
                      </label>
                      <input
                        type="password"
                        minLength="12"
                        value={form.password}
                        onChange={(event) =>
                          setForm({ ...form, password: event.target.value })
                        }
                        required
                      />
                      {errors.password && (
                        <span className="field-error">
                          {errors.password[0]}
                        </span>
                      )}
                    </div>
                    <div className="form-field">
                      <label>
                        Confirmation <span className="req">*</span>
                      </label>
                      <input
                        type="password"
                        minLength="12"
                        value={form.password_confirmation}
                        onChange={(event) =>
                          setForm({
                            ...form,
                            password_confirmation: event.target.value,
                          })
                        }
                        required
                      />
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
                    {submitting ? "Création…" : "Créer le compte"}
                  </button>
                </div>
              </form>
            ) : modal === "edit" ? (
              <form onSubmit={updateUser}>
                <div className="modal-body">
                  <div className="form-grid">
                    <div className="form-field">
                      <label>
                        Nom complet <span className="req">*</span>
                      </label>
                      <input
                        value={editForm.name}
                        onChange={(event) =>
                          setEditForm({ ...editForm, name: event.target.value })
                        }
                        required
                        autoFocus
                      />
                      {errors.name && (
                        <span className="field-error">{errors.name[0]}</span>
                      )}
                    </div>
                    <div className="form-field">
                      <label>
                        E-mail <span className="req">*</span>
                      </label>
                      <input
                        type="email"
                        value={editForm.email}
                        onChange={(event) =>
                          setEditForm({ ...editForm, email: event.target.value })
                        }
                        required
                      />
                      {errors.email && (
                        <span className="field-error">{errors.email[0]}</span>
                      )}
                    </div>
                    <div className="form-field full">
                      <label>
                        Département <span className="req">*</span>
                      </label>
                      <select
                        value={editForm.departement_id}
                        onChange={(event) =>
                          setEditForm({
                            ...editForm,
                            departement_id: event.target.value,
                          })
                        }
                        required
                      >
                        <option value="">Sélectionner un département</option>
                        {departements.map((department) => (
                          <option key={department.id} value={department.id}>
                            {department.nom}
                          </option>
                        ))}
                      </select>
                      {errors.departement_id && (
                        <span className="field-error">
                          {errors.departement_id[0]}
                        </span>
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
            ) : (
              <>
                <div className="modal-body permissions-editor">
                  <div className="form-field full">
                    <label>Attribuer un rôle</label>
                    <select defaultValue="" onChange={(event) => applyPack(event.target.value)}>
                      <option value="">Permissions personnalisées</option>
                      {packs.map((pack) => <option key={pack.id} value={pack.id}>{pack.nom}</option>)}
                    </select>
                  </div>
                  {Object.entries(groupedPermissions).map(([group, items]) => (
                    <section className={`permission-group${openPermissionGroups[group] ? " open" : ""}`} key={group}>
                      <div className="permission-group-title">
                        <input
                          type="checkbox"
                          checked={items.every((permission) =>
                            selectedPermissionIds.includes(permission.id),
                          )}
                          onChange={() => togglePermissionGroup(items)}
                        />
                        <button
                          type="button"
                          className="permission-group-toggle"
                          onClick={() => togglePermissionGroupOpen(group)}
                          aria-expanded={!!openPermissionGroups[group]}
                        >
                          <h3>{permissionGroups[group] || "Autres permissions"}</h3>
                          <ChevronRight size={17} />
                        </button>
                      </div>
                      {openPermissionGroups[group] && items.map((permission) => (
                        <label className="permission-option" key={permission.id}>
                          <input
                            type="checkbox"
                            checked={selectedPermissionIds.includes(permission.id)}
                            onChange={() => togglePermission(permission.id)}
                          />
                          <span><strong>{permission.libelle}</strong></span>
                        </label>
                      ))}
                    </section>
                  ))}
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
                    type="button"
                    className="btn-primary"
                    onClick={savePermissions}
                    disabled={submitting}
                  >
                    {submitting ? "Enregistrement…" : "Enregistrer"}
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      )}
    </div>
  );
}