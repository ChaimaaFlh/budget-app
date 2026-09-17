import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import LignesBudgetaires from "../LignesBudgetaires";
import api from "../../api/axios";
import { useAuth } from "../../context/AuthContext";
import { useToast } from "../../context/ToastContext";

vi.mock("../../api/axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));
vi.mock("../../context/AuthContext", () => ({ useAuth: vi.fn() }));
vi.mock("../../context/ToastContext", () => ({ useToast: vi.fn() }));

const departements = [
  { id: 1, nom: "Informatique" },
  { id: 2, nom: "Ressources Humaines" },
];

const cats = [
  {
    id: 1,
    nom: "Fournitures",
    sous_categories: [
      { id: 11, nom: "Papeterie" },
      { id: 12, nom: "Consommables" },
    ],
  },
  { id: 2, nom: "Matériel", sous_categories: [] },
];

const budgets = [
  { id: 50, code: "BUD-2026-001" },
  { id: 51, code: "BUD-2026-002" },
];

const sampleLignes = [
  {
    id: 200,
    code: "LB-2026-001",
    intitule: "Achat papeterie",
    categorie: { nom: "Fournitures" },
    sous_categorie: { nom: "Papeterie", categorie: { nom: "Fournitures" } },
    budget: { code: "BUD-2026-001" },
    montant_alloue: 10000,
    total_consomme: 4000,
    annuites: [{ id: 1, annee: 2026, montant: 10000 }],
    statut: "ouvert",
    categorie_id: 1,
    sous_categorie_id: 11,
    budget_id: 50,
    date_debut_amortissement: "2026-01-01",
    duree_amortissement_annees: 1,
    departement_id: 1,
  },
];

function mockLoad(lignes = sampleLignes) {
  api.get.mockImplementation((url) => {
    if (url === "/ligne-budgets") return Promise.resolve({ data: lignes });
    if (url === "/categories") return Promise.resolve({ data: cats });
    if (url === "/budgets") return Promise.resolve({ data: budgets });
    if (url === "/references/next/ligne")
      return Promise.resolve({ data: { reference: "LB-2027-042" } });
    return Promise.reject(new Error(`URL inattendue: ${url}`));
  });
}

function renderPage(initialEntry = "/lignes") {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <LignesBudgetaires />
    </MemoryRouter>,
  );
}

function fieldControl(container, labelText) {
  return within(container)
    .getByText(labelText)
    .closest(".form-field")
    .querySelector("select, input, textarea");
}

describe("LignesBudgetaires", () => {
  const showToast = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    useToast.mockReturnValue({ showToast });
    useAuth.mockReturnValue({
      hasPermission: () => true,
      canViewAllDepartments: () => true,
      departements,
    });
  });

  it("affiche le chargement puis la liste des lignes budgétaires", async () => {
    mockLoad();
    renderPage();

    expect(
      screen.getByText(/chargement des lignes budgétaires/i),
    ).toBeInTheDocument();

    await waitFor(() =>
      expect(screen.getByText("LB-2026-001")).toBeInTheDocument(),
    );
    expect(screen.getByText("Achat papeterie")).toBeInTheDocument();
    expect(screen.getByText("Fournitures › Papeterie")).toBeInTheDocument();
    expect(screen.getByRole("cell", { name: "BUD-2026-001" })).toBeInTheDocument();
    expect(screen.getByText("2026")).toBeInTheDocument();
    expect(screen.getByText("ouvert")).toBeInTheDocument();
  });

  it("affiche un message si aucune ligne n'est visible", async () => {
    mockLoad([]);
    renderPage();

    await waitFor(() =>
      expect(screen.getByText("Aucune ligne visible.")).toBeInTheDocument(),
    );
  });

  it("affiche un toast d'erreur si le chargement échoue", async () => {
    api.get.mockRejectedValue({
      response: { data: { message: "Erreur réseau." } },
    });
    renderPage();

    await waitFor(() =>
      expect(showToast).toHaveBeenCalledWith("Erreur réseau.", true),
    );
  });

  it("n'affiche pas le bouton Nouvelle ligne sans la permission ligne.create", async () => {
    mockLoad();
    useAuth.mockReturnValue({
      hasPermission: (code) => code !== "ligne.create",
      canViewAllDepartments: () => true,
      departements,
    });
    renderPage();

    await waitFor(() =>
      expect(screen.getByText("LB-2026-001")).toBeInTheDocument(),
    );
    expect(
      screen.queryByRole("button", { name: /nouvelle ligne/i }),
    ).not.toBeInTheDocument();
  });

  it("cache l'action modifier sans la permission ligne.edit", async () => {
    mockLoad();
    useAuth.mockReturnValue({
      hasPermission: (code) => code !== "ligne.edit",
      canViewAllDepartments: () => true,
      departements,
    });
    renderPage();

    await waitFor(() =>
      expect(screen.getByText("LB-2026-001")).toBeInTheDocument(),
    );
    const row = screen.getByText("LB-2026-001").closest("tr");
    expect(within(row).getByRole("link", { name: /détail/i })).toBeInTheDocument();
    expect(within(row).queryAllByRole("button")).toHaveLength(1);
  });

  it("cache l'action supprimer sans la permission ligne.delete", async () => {
    mockLoad();
    useAuth.mockReturnValue({
      hasPermission: (code) => code !== "ligne.delete",
      canViewAllDepartments: () => true,
      departements,
    });
    renderPage();

    await waitFor(() =>
      expect(screen.getByText("LB-2026-001")).toBeInTheDocument(),
    );
    const row = screen.getByText("LB-2026-001").closest("tr");
    expect(within(row).getByRole("link", { name: /détail/i })).toBeInTheDocument();
    expect(within(row).queryAllByRole("button")).toHaveLength(1);
  });

  it("ouvre la modale de création avec un code généré automatiquement", async () => {
    mockLoad();
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("LB-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouvelle ligne/i }));

    const modal = (await screen.findByText("Nouvelle ligne budgétaire")).closest(
      ".modal",
    );
    await waitFor(() =>
      expect(fieldControl(modal, "Code *")).toHaveValue("LB-2027-042"),
    );
  });

  it("met à jour les sous-catégories disponibles selon la catégorie choisie", async () => {
    mockLoad();
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("LB-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouvelle ligne/i }));
    const modal = (await screen.findByText("Nouvelle ligne budgétaire")).closest(
      ".modal",
    );

    const sousCategorieSelect = fieldControl(
      modal,
      "Sous-catégorie (optionnel)",
    );
    expect(sousCategorieSelect).toBeDisabled();

    await user.selectOptions(fieldControl(modal, "Catégorie *"), "1");

    expect(sousCategorieSelect).toBeEnabled();
    expect(within(modal).getByText("Papeterie")).toBeInTheDocument();
    expect(within(modal).getByText("Consommables")).toBeInTheDocument();
  });

  it("crée une ligne budgétaire et recharge la liste", async () => {
    mockLoad();
    api.post.mockResolvedValueOnce({});
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("LB-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouvelle ligne/i }));
    const modal = (await screen.findByText("Nouvelle ligne budgétaire")).closest(
      ".modal",
    );

    await user.selectOptions(fieldControl(modal, "Budget *"), "50");
    await user.type(
      fieldControl(modal, "Intitulé *"),
      "Fournitures diverses",
    );
    await user.selectOptions(fieldControl(modal, "Catégorie *"), "1");
    await user.selectOptions(
      fieldControl(modal, "Sous-catégorie (optionnel)"),
      "11",
    );
    await user.type(fieldControl(modal, "Montant *"), "3000");
    await user.selectOptions(fieldControl(modal, "Département"), "1");

    await user.click(within(modal).getByRole("button", { name: /enregistrer/i }));

    await waitFor(() => expect(api.post).toHaveBeenCalledTimes(1));
    expect(api.post).toHaveBeenCalledWith(
      "/ligne-budgets",
      expect.objectContaining({
        budget_id: 50,
        categorie_id: 1,
        sous_categorie_id: 11,
        montant_alloue: 3000,
        intitule: "Fournitures diverses",
      }),
    );
    expect(showToast).toHaveBeenCalledWith("Ligne créée.");
  });

  it("affiche les erreurs de validation (422) lors de la création", async () => {
    mockLoad();
    api.post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { errors: { categorie_id: ["Cette catégorie n'existe pas."] } },
      },
    });
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("LB-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouvelle ligne/i }));
    const modal = (await screen.findByText("Nouvelle ligne budgétaire")).closest(
      ".modal",
    );

    await user.selectOptions(fieldControl(modal, "Budget *"), "50");
    await user.type(fieldControl(modal, "Intitulé *"), "Achat");
    await user.selectOptions(fieldControl(modal, "Catégorie *"), "1");
    await user.type(fieldControl(modal, "Montant *"), "1000");

    await user.click(within(modal).getByRole("button", { name: /enregistrer/i }));

    expect(
      await within(modal).findByText("Cette catégorie n'existe pas."),
    ).toBeInTheDocument();
  });

  it("ouvre la modale d'édition pré-remplie et modifie la ligne", async () => {
    mockLoad();
    api.put.mockResolvedValueOnce({});
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("LB-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    const row = screen.getByText("LB-2026-001").closest("tr");
    await user.click(within(row).getAllByRole("button")[0]); // crayon = modifier

    const modal = (await screen.findByText("Modifier ligne budgétaire")).closest(
      ".modal",
    );
    expect(fieldControl(modal, "Code *")).toHaveValue("LB-2026-001");
    expect(fieldControl(modal, "Montant *")).toHaveValue(10000);

    const montantInput = fieldControl(modal, "Montant *");
    await user.clear(montantInput);
    await user.type(montantInput, "12000");
    await user.click(within(modal).getByRole("button", { name: /enregistrer/i }));

    await waitFor(() =>
      expect(api.put).toHaveBeenCalledWith(
        "/ligne-budgets/200",
        expect.objectContaining({ montant_alloue: 12000 }),
      ),
    );
    expect(showToast).toHaveBeenCalledWith("Ligne modifiée.");
  });

  it("supprime une ligne après confirmation", async () => {
    mockLoad();
    vi.spyOn(window, "confirm").mockReturnValue(true);
    api.delete.mockResolvedValueOnce({});
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("LB-2026-001")).toBeInTheDocument(),
    );

    const row = screen.getByText("LB-2026-001").closest("tr");
    const user = userEvent.setup();
    await user.click(within(row).getAllByRole("button")[1]); // corbeille = supprimer

    await waitFor(() =>
      expect(api.delete).toHaveBeenCalledWith("/ligne-budgets/200"),
    );
    expect(showToast).toHaveBeenCalledWith("Ligne supprimée.");
  });

  it("n'appelle pas l'API si la suppression n'est pas confirmée", async () => {
    mockLoad();
    vi.spyOn(window, "confirm").mockReturnValue(false);
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("LB-2026-001")).toBeInTheDocument(),
    );

    const row = screen.getByText("LB-2026-001").closest("tr");
    const user = userEvent.setup();
    await user.click(within(row).getAllByRole("button")[1]);

    expect(api.delete).not.toHaveBeenCalled();
  });

  it("ouvre automatiquement la modale de création via les paramètres d'URL", async () => {
    mockLoad();
    renderPage("/lignes?create=1&budget=51&departement=2");

    const modal = (await screen.findByText("Nouvelle ligne budgétaire")).closest(
      ".modal",
    );
    expect(fieldControl(modal, "Budget *")).toHaveValue("51");
    expect(fieldControl(modal, "Département")).toHaveValue("2");
  });

  it("n'affiche pas le champ Département sans la permission de voir tous les départements", async () => {
    mockLoad();
    useAuth.mockReturnValue({
      hasPermission: () => true,
      canViewAllDepartments: () => false,
      departements,
    });
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("LB-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouvelle ligne/i }));
    const modal = (await screen.findByText("Nouvelle ligne budgétaire")).closest(
      ".modal",
    );

    expect(within(modal).queryByText("Département")).not.toBeInTheDocument();
  });
});