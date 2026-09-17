import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import Budgets from "../Budgets";
import api from "../../api/axios";
import { useAuth } from "../../context/AuthContext";
import { useToast } from "../../context/ToastContext";

vi.mock("../../api/axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));
vi.mock("../../context/AuthContext", () => ({ useAuth: vi.fn() }));
vi.mock("../../context/ToastContext", () => ({ useToast: vi.fn() }));

const mockNavigate = vi.fn();
vi.mock("react-router-dom", async (importOriginal) => {
  const actual = await importOriginal();
  return { ...actual, useNavigate: () => mockNavigate };
});

const departements = [
  { id: 1, nom: "Informatique" },
  { id: 2, nom: "Ressources Humaines" },
];

const sampleBudgets = [
  {
    id: 10,
    code: "BUD-2026-001",
    nom: "Budget Informatique",
    montant_global: 100000,
    total_alloue: 40000,
    annee: 2026,
    type: "fonctionnement",
    statut: "ouvert",
  },
];

const sampleLignes = [{ id: 1, budget_id: 10, departement_id: 1 }];

function mockLoad(budgets = sampleBudgets, lignes = sampleLignes) {
  api.get.mockImplementation((url) => {
    if (url === "/budgets") return Promise.resolve({ data: budgets });
    if (url === "/ligne-budgets") return Promise.resolve({ data: lignes });
    if (url === "/references/next/budget")
      return Promise.resolve({ data: { reference: "BUD-2027-003" } });
    return Promise.reject(new Error(`URL inattendue: ${url}`));
  });
}

function renderPage() {
  return render(
    <MemoryRouter>
      <Budgets />
    </MemoryRouter>,
  );
}

function kpiValue(label) {
  return screen
    .getByText(label)
    .closest(".budget-kpi")
    .querySelector("strong").textContent;
}

describe("Budgets", () => {
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

  it("affiche le chargement puis la liste des budgets avec les KPIs", async () => {
    mockLoad();
    renderPage();

    expect(screen.getByText(/chargement des budgets/i)).toBeInTheDocument();

    await waitFor(() =>
      expect(screen.getByText("BUD-2026-001")).toBeInTheDocument(),
    );
    expect(screen.getByText("Budget Informatique")).toBeInTheDocument();
    expect(kpiValue("Budgets visibles")).toBe("1");
  });

  it("affiche un message si aucun budget n'est visible", async () => {
    mockLoad([], []);
    renderPage();

    await waitFor(() =>
      expect(screen.getByText("Aucun budget visible.")).toBeInTheDocument(),
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

  it("n'affiche pas le bouton Nouveau budget sans la permission requise", async () => {
    mockLoad();
    useAuth.mockReturnValue({
      hasPermission: () => false,
      canViewAllDepartments: () => true,
      departements,
    });
    renderPage();

    await waitFor(() =>
      expect(screen.getByText("BUD-2026-001")).toBeInTheDocument(),
    );
    expect(
      screen.queryByRole("button", { name: /nouveau budget/i }),
    ).not.toBeInTheDocument();
  });

  it("ouvre la modale de création avec un code généré automatiquement", async () => {
    mockLoad();
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BUD-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouveau budget/i }));

    expect(api.get).toHaveBeenCalledWith("/references/next/budget");
    const dialog = await screen.findByRole("dialog");
    expect(
      within(dialog).getByDisplayValue("BUD-2027-003"),
    ).toBeInTheDocument();
  });

  it("crée un budget et redirige vers la page des lignes budgétaires", async () => {
    mockLoad();
    api.post.mockResolvedValueOnce({ data: { data: { id: 99 } } });
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BUD-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouveau budget/i }));
    const dialog = await screen.findByRole("dialog");

    const nomInput = dialog.querySelector(".form-field.full input");
    await user.type(nomInput, "Budget RH 2027");

    const montantInput = dialog.querySelectorAll('input[type="number"]')[1];
    await user.type(montantInput, "50000");

    await user.click(
      within(dialog).getByRole("button", {
        name: /sélectionner un ou plusieurs départements/i,
      }),
    );
    await user.click(
      within(dialog).getByRole("checkbox", { name: "Ressources Humaines" }),
    );

    await user.click(within(dialog).getByRole("button", { name: /enregistrer/i }));

    await waitFor(() => expect(api.post).toHaveBeenCalledTimes(1));
    expect(api.post).toHaveBeenCalledWith(
      "/budgets",
      expect.objectContaining({
        nom: "Budget RH 2027",
        montant_global: 50000,
        code: "BUD-2027-003",
      }),
    );
    expect(mockNavigate).toHaveBeenCalledWith(
      expect.stringContaining("/lignes?create=1&budget=99&departement=2"),
    );
  });

  it("affiche les erreurs de validation (422) lors de la création", async () => {
    mockLoad();
    api.post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { errors: { nom: ["Le nom est déjà utilisé."] } },
      },
    });
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BUD-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouveau budget/i }));
    const dialog = await screen.findByRole("dialog");

    const nomInput = dialog.querySelector(".form-field.full input");
    await user.type(nomInput, "Budget en doublon");
    const montantInput = dialog.querySelectorAll('input[type="number"]')[1];
    await user.type(montantInput, "1000");
    await user.click(
      within(dialog).getByRole("button", {
        name: /sélectionner un ou plusieurs départements/i,
      }),
    );
    await user.click(
      within(dialog).getByRole("checkbox", { name: "Informatique" }),
    );

    await user.click(within(dialog).getByRole("button", { name: /enregistrer/i }));

    expect(
      await within(dialog).findByText("Le nom est déjà utilisé."),
    ).toBeInTheDocument();
    // La modale reste ouverte en cas d'erreur de validation
    expect(screen.getByRole("dialog")).toBeInTheDocument();
  });

  it("ouvre la modale d'édition pré-remplie et modifie le budget", async () => {
    mockLoad();
    api.put.mockResolvedValueOnce({});
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BUD-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /modifier/i }));
    const dialog = await screen.findByRole("dialog");

    expect(
      within(dialog).getByDisplayValue("BUD-2026-001"),
    ).toBeInTheDocument();
    expect(within(dialog).getByDisplayValue("Budget Informatique"))
      .toBeInTheDocument();
    // L'année ne doit plus être modifiable en édition
    const anneeInput = dialog.querySelectorAll('input[type="number"]')[0];
    expect(anneeInput).toBeDisabled();

    const nomInput = dialog.querySelector(".form-field.full input");
    await user.clear(nomInput);
    await user.type(nomInput, "Budget Informatique modifié");
    await user.click(within(dialog).getByRole("button", { name: /enregistrer/i }));

    await waitFor(() =>
      expect(api.put).toHaveBeenCalledWith(
        "/budgets/10",
        expect.objectContaining({ nom: "Budget Informatique modifié" }),
      ),
    );
    expect(showToast).toHaveBeenCalledWith("Budget modifié.");
  });

  it("supprime un budget après confirmation", async () => {
    mockLoad();
    vi.spyOn(window, "confirm").mockReturnValue(true);
    api.delete.mockResolvedValueOnce({});
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BUD-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /supprimer/i }));

    await waitFor(() => expect(api.delete).toHaveBeenCalledWith("/budgets/10"));
    expect(showToast).toHaveBeenCalledWith("Budget supprimé.");
  });

  it("n'appelle pas l'API si la suppression n'est pas confirmée", async () => {
    mockLoad();
    vi.spyOn(window, "confirm").mockReturnValue(false);
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BUD-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /supprimer/i }));

    expect(api.delete).not.toHaveBeenCalled();
  });

  it("clôture un budget après confirmation", async () => {
    mockLoad();
    vi.spyOn(window, "confirm").mockReturnValue(true);
    api.post.mockResolvedValueOnce({});
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BUD-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /clôturer/i }));

    await waitFor(() =>
      expect(api.post).toHaveBeenCalledWith("/budgets/10/close"),
    );
    expect(showToast).toHaveBeenCalledWith("Budget clôturé.");
  });

  it("ne propose pas de clôturer un budget déjà clos", async () => {
    mockLoad([{ ...sampleBudgets[0], statut: "clos" }], sampleLignes);
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BUD-2026-001")).toBeInTheDocument(),
    );

    expect(
      screen.queryByRole("button", { name: /clôturer/i }),
    ).not.toBeInTheDocument();
  });
});