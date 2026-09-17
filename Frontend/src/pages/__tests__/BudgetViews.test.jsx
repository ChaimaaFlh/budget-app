import { beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import Dashboard from "../Dashboard";
import Rapports from "../Rapports";
import ArborescenceBudgetaire from "../ArborescenceBudgetaire";
import DetailLigneBudgetaire from "../DetailLigneBudgetaire";
import api from "../../api/axios";
import { useAuth } from "../../context/AuthContext";
import { useToast } from "../../context/ToastContext";

vi.mock("../../api/axios", () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } }));
vi.mock("../../context/AuthContext", () => ({ useAuth: vi.fn() }));
vi.mock("../../context/ToastContext", () => ({ useToast: vi.fn() }));
const navigate = vi.fn();
vi.mock("react-router-dom", async (original) => ({ ...(await original()), useNavigate: () => navigate }));
const toast = vi.fn();
const lines = [{ id: 1, code: "LB-01", intitule: "Matériel", montant_alloue: 1000, total_consomme: 800, statut: "Totalement Consommé", departement_id: 1, categorie_id: 10, sous_categorie_id: 20, sous_categorie: { nom: "IT", categorie_id: 10, categorie: { nom: "Informatique" } }, budget: { type: "investissement" } }, { id: 2, code: "LB-02", intitule: "Bureau", montant_alloue: 500, total_consomme: 100, statut: "Disponible", departement_id: 2, categorie_id: 11, categorie: { nom: "Services" } }];
const categories = [{ id: 10, nom: "Informatique", sous_categories: [{ id: 20, nom: "IT" }] }, { id: 11, nom: "Services", sous_categories: [] }];
const renderPage = (node) => render(<MemoryRouter>{node}</MemoryRouter>);
beforeEach(() => { vi.clearAllMocks(); useToast.mockReturnValue({ showToast: toast }); useAuth.mockReturnValue({ departement: { id: 1, nom: "Finance" }, departements: [{ id: 1, nom: "Finance" }, { id: 2, nom: "Achats" }], canViewAllDepartments: () => true, hasPermission: () => true }); });

describe("Dashboard", () => {
  const load = () => api.get.mockImplementation((url) => Promise.resolve({ data: ({ "/budgets": [], "/ligne-budgets": lines, "/bons-commande": [{ id: 3, numero_bc: "BC-1", intitule_bc: "PC", fournisseur: "Acme", ligne_budget_id: 1, montant_bc: 800, statut: "brouillon", departement_id: 1 }], "/sous-categories": [{ id: 20, categorie: { nom: "Informatique" } }] })[url] }));
  it("calcule les indicateurs, le graphique et les bons récents", async () => {
    load(); renderPage(<Dashboard />);
    expect(await screen.findByText(/tableau de bord/i)).toBeInTheDocument();
    expect(screen.getByText("LB-01")).toBeInTheDocument();
    expect(screen.getByText("Informatique")).toBeInTheDocument();
  });
  it("filtre les données par département pour un administrateur", async () => {
    load(); renderPage(<Dashboard />); await screen.findByText("LB-01");
    await userEvent.setup().selectOptions(screen.getByRole("combobox"), "2");
    expect(screen.queryByText("BC-1")).not.toBeInTheDocument();
    expect(screen.getByText(/^1 ligne\(s\) budgétaire/)).toBeInTheDocument();
  });
  it("notifie en cas d'échec de chargement", async () => { api.get.mockRejectedValue(new Error("offline")); renderPage(<Dashboard />); await waitFor(() => expect(toast).toHaveBeenCalledWith("Impossible de charger le tableau de bord.", true)); });
});

describe("Rapports", () => {
  const load = () => api.get.mockImplementation((url) => Promise.resolve({ data: url === "/ligne-budgets" ? lines : categories }));
  it("présente le détail et filtre par catégorie", async () => {
    load(); renderPage(<Rapports />); await screen.findByText("LB-01");
    await userEvent.setup().click(screen.getByRole("button", { name: "Informatique" }));
    expect(screen.getByText("LB-01")).toBeInTheDocument(); expect(screen.queryByText("LB-02")).not.toBeInTheDocument();
  });
  it("recharge les données lorsque la fenêtre reprend le focus", async () => {
    load(); renderPage(<Rapports />); await screen.findByText("Rapports");
    window.dispatchEvent(new Event("focus")); await waitFor(() => expect(api.get).toHaveBeenCalledTimes(4));
  });
});

describe("ArborescenceBudgetaire", () => {
  const load = () => api.get.mockImplementation((url) => Promise.resolve({ data: url === "/categories" ? categories : lines }));
  it("affiche les catégories et navigue vers le détail d'une ligne", async () => {
    load(); renderPage(<ArborescenceBudgetaire />); await screen.findByText("Informatique");
    await userEvent.setup().click(screen.getAllByRole("button", { name: /détail/i })[0]); expect(navigate).toHaveBeenCalledWith("/lignes/1");
  });
  it("crée une sous-catégorie et gère les erreurs 422", async () => {
    load(); api.post.mockRejectedValue({ response: { status: 422, data: { errors: { nom: ["Déjà utilisé"] } } } }); renderPage(<ArborescenceBudgetaire />); await screen.findByText("Informatique");
    const user = userEvent.setup(); await user.click(screen.getAllByRole("button", { name: /sous-catégorie/i })[0]); const dialog = screen.getByRole("dialog"); await user.type(within(dialog).getByRole("textbox"), "Réseau"); await user.click(within(dialog).getByRole("button", { name: /enregistrer/i }));
    expect(await screen.findByText("Déjà utilisé")).toBeInTheDocument();
  });
  it("supprime une catégorie après confirmation", async () => { load(); api.delete.mockResolvedValue({}); vi.spyOn(window, "confirm").mockReturnValue(true); renderPage(<ArborescenceBudgetaire />); await screen.findByText("Informatique"); await userEvent.setup().click(screen.getByRole("button", { name: /supprimer la catégorie informatique/i })); await waitFor(() => expect(api.delete).toHaveBeenCalledWith("/categories/10")); });
});

describe("DetailLigneBudgetaire", () => {
  const line = { ...lines[0], date_debut_amortissement: "2026-01-01", duree_amortissement_annees: 2 };
  const load = () => api.get.mockImplementation((url) => Promise.resolve({ data: url === "/ligne-budgets/1" ? line : url === "/ligne-budgets/1/annuites" ? [{ id: 1, annee: 2026, montant: 500 }, { id: 2, annee: 2027, montant: 500 }] : url === "/bons-commande" ? [{ id: 8, ligne_budget_id: 1, numero_bc: "BC-8", fournisseur: "Acme", montant_bc: 800 }] : [{ id: 9, ref_facture: "FAC-9", montant: 800 }] }));
  const page = () => render(<MemoryRouter initialEntries={["/lignes/1"]}><Routes><Route path="/lignes/:id" element={<DetailLigneBudgetaire />} /></Routes></MemoryRouter>);
  it("charge la ligne, ses annuités et les éléments liés", async () => {
  load(); page();
  expect(await screen.findByText("Matériel")).toBeInTheDocument();
  expect(screen.getAllByText("BC-8").length).toBeGreaterThan(0);
  expect(screen.getByText("FAC-9")).toBeInTheDocument();
});
  it("présente la répartition annuelle sans permettre de la modifier", async () => { load(); page(); await screen.findByText("Matériel"); expect(screen.getByRole("heading", { name: "Annuités" })).toBeInTheDocument(); expect(screen.queryByRole("button", { name: /modifier/i })).not.toBeInTheDocument(); });
});
