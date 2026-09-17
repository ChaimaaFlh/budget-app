import { beforeEach, describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import Sidebar from "../Layout/Sidebar";
import Topbar from "../Layout/Topbar";
import Layout from "../Layout/Layout";
import FactureDocumentsModal from "../Factures/FactureDocumentsModal";
import api from "../../api/axios";
import { useAuth } from "../../context/AuthContext";
import { useToast } from "../../context/ToastContext";

vi.mock("../../api/axios", () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }));
vi.mock("../../context/AuthContext", () => ({ useAuth: vi.fn() }));
vi.mock("../../context/ToastContext", () => ({ useToast: vi.fn() }));
const navigate = vi.fn();
vi.mock("react-router-dom", async (original) => ({ ...(await original()), useNavigate: () => navigate }));
const toast = vi.fn();
const renderPage = (node, path = "/") => render(<MemoryRouter initialEntries={[path]}>{node}</MemoryRouter>);
beforeEach(() => { vi.clearAllMocks(); useToast.mockReturnValue({ showToast: toast }); useAuth.mockReturnValue({ user: { id: 1, name: "Amina" }, departement: { nom: "Finance" }, permissions: [{ id: 1 }], canViewAllDepartments: () => true, isSysAdmin: () => true, hasPermission: () => false, logout: vi.fn() }); });

describe("Sidebar, Topbar et Layout", () => {
  it("adapte les liens de la barre latérale aux droits et ouvre le profil", async () => {
    const onNavigate = vi.fn(); renderPage(<Sidebar open onNavigate={onNavigate} />);
    expect(screen.getByRole("link", { name: /départements/i })).toBeInTheDocument(); expect(screen.getByRole("link", { name: /utilisateurs/i })).toBeInTheDocument();
    await userEvent.setup().click(screen.getByRole("button", { name: /amina/i })); expect(navigate).toHaveBeenCalledWith("/profil");
    await userEvent.setup().click(screen.getByRole("link", { name: /budgets/i })); expect(onNavigate).toHaveBeenCalled();
  });
  it("déclenche le menu, le profil et la déconnexion depuis la barre supérieure", async () => {
    const toggle = vi.fn(); const logout = vi.fn().mockResolvedValue({}); useAuth.mockReturnValue({ user: { name: "Amina" }, departement: { nom: "Finance" }, logout }); renderPage(<Topbar onToggleSidebar={toggle} />);
    const user = userEvent.setup(); await user.click(screen.getByRole("button", { name: "Menu" })); expect(toggle).toHaveBeenCalled(); await user.click(screen.getByRole("button", { name: /mon profil/i })); expect(navigate).toHaveBeenCalledWith("/profil"); await user.click(screen.getByRole("button", { name: /se déconnecter/i })); await waitFor(() => expect(toast).toHaveBeenCalledWith("Déconnecté."));
  });
  it("referme la barre latérale après clic sur le voile", async () => {
    renderPage(<Routes><Route element={<Layout />}><Route index element={<div>Contenu</div>} /></Route></Routes>);
    const user = userEvent.setup(); await user.click(screen.getByRole("button", { name: "Menu" })); expect(document.querySelector(".sidebar-overlay")).toHaveClass("open"); await user.click(document.querySelector(".sidebar-overlay")); expect(document.querySelector(".sidebar-overlay")).not.toHaveClass("open");
  });
});

describe("FactureDocumentsModal", () => {
  const facture = { id: 7, bon_commande_id: 4, ref_facture: "FAC-7" };
  const url = "/bons-commande/4/factures/7/documents";
  const renderModal = (props = {}) => renderPage(<FactureDocumentsModal facture={facture} canManage onClose={vi.fn()} onChanged={vi.fn()} {...props} />);
  it("charge et affiche les documents existants", async () => { api.get.mockResolvedValue({ data: [{ id: 1, nom_original: "facture.pdf", type: "scan_facture", taille_octets: 2048 }] }); renderModal(); expect(await screen.findByText("facture.pdf")).toBeInTheDocument(); expect(document.querySelector(".doc-badge.scan")).toHaveTextContent("Scan facture"); });
  it("refuse les formats et tailles non autorisés", async () => { api.get.mockResolvedValue({ data: [] }); renderModal(); await screen.findByText(/aucun document/i); const input = document.querySelector('input[type="file"]'); fireEvent.change(input, { target: { files: [new File(["x"], "virus.exe", { type: "application/octet-stream" })], value: "" } }); expect(screen.getByText(/format non accepté/i)).toBeInTheDocument(); });
  it("envoie les fichiers valides sous forme multipart puis recharge", async () => { api.get.mockResolvedValue({ data: [] }); api.post.mockResolvedValue({}); const changed = vi.fn(); renderModal({ onChanged: changed }); await screen.findByText(/aucun document/i); const input = document.querySelector('input[type="file"]'); await userEvent.setup().upload(input, new File(["pdf"], "facture.pdf", { type: "application/pdf" })); await userEvent.setup().click(screen.getByRole("button", { name: /ajouter les documents/i })); await waitFor(() => expect(api.post).toHaveBeenCalledWith(url, expect.any(FormData), expect.any(Object))); expect(changed).toHaveBeenCalled(); });
  it("supprime un document après confirmation", async () => { api.get.mockResolvedValue({ data: [{ id: 1, nom_original: "facture.pdf", type: "scan_facture", taille_octets: 1 }] }); api.delete.mockResolvedValue({}); vi.spyOn(window, "confirm").mockReturnValue(true); renderModal(); await screen.findByText("facture.pdf"); await userEvent.setup().click(screen.getByTitle(/supprimer/i)); await waitFor(() => expect(api.delete).toHaveBeenCalledWith(`${url}/1`)); });
  it("notifie si le chargement échoue", async () => { api.get.mockRejectedValue({ response: { data: { message: "Erreur documents" } } }); renderModal(); await waitFor(() => expect(toast).toHaveBeenCalledWith("Erreur documents", true)); });
});