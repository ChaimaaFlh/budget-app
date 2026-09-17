import { useCallback, useEffect, useState } from "react";
import { Download, FileText, Paperclip, Trash2, Upload, X } from "lucide-react";
import api from "../../api/axios";
import { useToast } from "../../context/ToastContext";
import "../../styles/FactureDocuments.css";

const MAX_DOCUMENTS = 5;
const MAX_TAILLE_OCTETS = 10 * 1024 * 1024; // 10 Mo
const TYPES_ACCEPTES = ["application/pdf", "image/jpeg", "image/jpg", "image/png"];

const formatTaille = (octets) => {
  if (!octets) return "—";
  if (octets < 1024 * 1024) return `${Math.round(octets / 1024)} Ko`;
  return `${(octets / (1024 * 1024)).toFixed(1)} Mo`;
};

const libelleType = (type) =>
  type === "scan_facture" ? "Scan facture" : "Pièce justificative";

export default function FactureDocumentsModal({
  facture,
  canManage,
  onClose,
  onChanged,
}) {
  const { showToast } = useToast();
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [scanFile, setScanFile] = useState(null);
  const [justifFiles, setJustifFiles] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState("");

  const bonId = facture.bon_commande_id;
  const factureId = facture.id;

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await api.get(
        `/bons-commande/${bonId}/factures/${factureId}/documents`,
      );
      setDocuments(data);
    } catch (e) {
      showToast(
        e.response?.data?.message || "Impossible de charger les documents.",
        true,
      );
    } finally {
      setLoading(false);
    }
  }, [bonId, factureId, showToast]);

  useEffect(() => {
    load();
  }, [load]);

  const dejaUnScan = documents.some((doc) => doc.type === "scan_facture");
  const emplacementsRestants =
    MAX_DOCUMENTS - documents.length - (scanFile ? 1 : 0) - justifFiles.length;

  const validerFichier = (file) => {
    if (!TYPES_ACCEPTES.includes(file.type)) {
      return `« ${file.name} » : format non accepté (PDF, JPG ou PNG uniquement).`;
    }
    if (file.size > MAX_TAILLE_OCTETS) {
      return `« ${file.name} » dépasse la taille maximale de 10 Mo.`;
    }
    return null;
  };

  const handleScanChange = (event) => {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;
    const erreur = validerFichier(file);
    if (erreur) {
      setError(erreur);
      return;
    }
    setError("");
    setScanFile(file);
  };

  const handleJustifChange = (event) => {
    const files = Array.from(event.target.files || []);
    event.target.value = "";
    if (!files.length) return;

    if (files.length > emplacementsRestants) {
      setError(
        `Tu ne peux ajouter que ${emplacementsRestants} document(s) supplémentaire(s) (limite de ${MAX_DOCUMENTS} par facture).`,
      );
      return;
    }

    for (const file of files) {
      const erreur = validerFichier(file);
      if (erreur) {
        setError(erreur);
        return;
      }
    }

    setError("");
    setJustifFiles((prev) => [...prev, ...files]);
  };

  const removeJustifFile = (index) => {
    setJustifFiles((prev) => prev.filter((_, i) => i !== index));
  };

  const upload = async () => {
    if (!scanFile && justifFiles.length === 0) return;

    const formData = new FormData();
    const types = [];
    if (scanFile) {
      formData.append("documents[]", scanFile);
      types.push("scan_facture");
    }
    justifFiles.forEach((file) => {
      formData.append("documents[]", file);
      types.push("piece_justificative");
    });
    types.forEach((type) => formData.append("types[]", type));

    setUploading(true);
    setError("");
    try {
      await api.post(
        `/bons-commande/${bonId}/factures/${factureId}/documents`,
        formData,
        { headers: { "Content-Type": "multipart/form-data" } },
      );
      showToast("Document(s) ajouté(s).");
      setScanFile(null);
      setJustifFiles([]);
      await load();
      onChanged?.();
    } catch (e) {
      setError(
        e.response?.data?.message ||
          Object.values(e.response?.data?.errors || {})[0]?.[0] ||
          "Échec de l'envoi des documents.",
      );
    } finally {
      setUploading(false);
    }
  };

  const downloadDocument = async (doc) => {
    try {
      const response = await api.get(
        `/bons-commande/${bonId}/factures/${factureId}/documents/${doc.id}/download`,
        { responseType: "blob" },
      );
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement("a");
      link.href = url;
      link.download = doc.nom_original;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch {
      showToast("Impossible de télécharger ce document.", true);
    }
  };

  const removeDocument = async (doc) => {
    if (!window.confirm(`Supprimer « ${doc.nom_original} » ?`)) return;
    try {
      await api.delete(
        `/bons-commande/${bonId}/factures/${factureId}/documents/${doc.id}`,
      );
      showToast("Document supprimé.");
      await load();
      onChanged?.();
    } catch (e) {
      showToast(
        e.response?.data?.message || "Suppression impossible.",
        true,
      );
    }
  };

  return (
    <div className="modal-overlay active" onMouseDown={onClose}>
      <div
        className="modal consumption-modal facture-docs-modal"
        role="dialog"
        aria-modal="true"
        onMouseDown={(e) => e.stopPropagation()}
      >
        <div className="modal-header">
          <div>
            <h2>Documents — {facture.ref_facture}</h2>
            <p>
              Scan de la facture + pièces justificatives. {MAX_DOCUMENTS}{" "}
              fichiers max, 10 Mo par fichier.
            </p>
          </div>
          <button className="modal-close" onClick={onClose}>
            <X size={18} />
          </button>
        </div>

        <div className="modal-body">
          {loading ? (
            <div className="docs-loading">Chargement des documents…</div>
          ) : (
            <div className="docs-list">
              {documents.length ? (
                documents.map((doc) => (
                  <div className="doc-row" key={doc.id}>
                    <FileText size={16} className="doc-icon" />
                    <div className="doc-info">
                      <strong>{doc.nom_original}</strong>
                      <span>
                        <span
                          className={`doc-badge ${
                            doc.type === "scan_facture" ? "scan" : "justif"
                          }`}
                        >
                          {libelleType(doc.type)}
                        </span>
                        {formatTaille(doc.taille_octets)}
                      </span>
                    </div>
                    <div className="doc-actions">
                      <button
                        className="btn-ghost btn-mini"
                        onClick={() => downloadDocument(doc)}
                        title="Télécharger"
                      >
                        <Download size={14} />
                      </button>
                      {canManage && (
                        <button
                          className="btn-ghost btn-mini btn-danger"
                          onClick={() => removeDocument(doc)}
                          title="Supprimer"
                        >
                          <Trash2 size={14} />
                        </button>
                      )}
                    </div>
                  </div>
                ))
              ) : (
                <div className="docs-empty">Aucun document lié à cette facture.</div>
              )}
            </div>
          )}

          {canManage && (
            <div className="docs-upload">
              <div className="docs-upload-row">
                <div>
                  <label className="docs-upload-label">
                    <Upload size={14} /> Scan facture
                  </label>
                  {dejaUnScan ? (
                    <p className="docs-hint">Déjà présent — supprime-le pour le remplacer.</p>
                  ) : scanFile ? (
                    <div className="docs-pending-file">
                      <span>{scanFile.name}</span>
                      <button
                        type="button"
                        className="btn-ghost btn-mini"
                        onClick={() => setScanFile(null)}
                      >
                        <X size={12} />
                      </button>
                    </div>
                  ) : (
                    <input
                      type="file"
                      accept=".pdf,.jpg,.jpeg,.png"
                      onChange={handleScanChange}
                      disabled={emplacementsRestants <= 0}
                    />
                  )}
                </div>

                <div>
                  <label className="docs-upload-label">
                    <Paperclip size={14} /> Pièces justificatives
                  </label>
                  {justifFiles.length > 0 && (
                    <ul className="docs-pending-list">
                      {justifFiles.map((file, index) => (
                        <li key={`${file.name}-${index}`}>
                          <span>{file.name}</span>
                          <button
                            type="button"
                            className="btn-ghost btn-mini"
                            onClick={() => removeJustifFile(index)}
                          >
                            <X size={12} />
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                  <input
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    multiple
                    onChange={handleJustifChange}
                    disabled={emplacementsRestants <= 0}
                  />
                </div>
              </div>

              <p className="docs-hint">
                {emplacementsRestants > 0
                  ? `${emplacementsRestants} emplacement(s) restant(s) sur ${MAX_DOCUMENTS}.`
                  : "Limite de documents atteinte pour cette facture."}
              </p>

              {error && <span className="field-error">{error}</span>}

              <button
                type="button"
                className="btn-primary"
                disabled={uploading || (!scanFile && justifFiles.length === 0)}
                onClick={upload}
              >
                {uploading ? "Envoi…" : "Ajouter les documents"}
              </button>
            </div>
          )}
        </div>

        <div className="modal-footer">
          <button type="button" className="btn-ghost" onClick={onClose}>
            Fermer
          </button>
        </div>
      </div>
    </div>
  );
}