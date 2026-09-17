import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import BonsCommande from "../BonsCommande";
import api from "../../api/axios";
import { useAuth } from "../../context/AuthContext";
import { useToast } from "../../context/ToastContext";

vi.mock("../../api/axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));
vi.mock("../../context/AuthContext", () => ({ useAuth: vi.fn() }));
vi.mock("../../context/ToastContext", () => ({ useToast: vi.fn() }));

const sampleLignes = [
  { id: 1, code: "LB-2026-001", intitule: "Fournitures" },
  { id: 2, code: "LB-2026-002", intitule: "Matériel" },
];

const sampleBons = [
  {
    id: 100,
    numero_bc: "BC-2026-001",
    intitule_bc: "Achat ordinateurs",
    fournisseur: "Dell",
    fournisseur_id: 1,
    ligne_budget_id: 1,
    ligne_budget: { code: "LB-2026-001" },
    montant_bc: 20000,
    date_achat: "2026-03-15",
    statut: "brouillon",
  },
  {
    id: 101,
    numero_bc: "BC-2026-002",
    intitule_bc: "Fournitures bureau",
    fournisseur: "Office Plus",
    fournisseur_id: 2,
    ligne_budget_id: 2,
    ligne_budget: { code: "LB-2026-002" },
    montant_bc: 5000,
    date_achat: "2026-04-01",
    statut: "envoye",
  },
];

const sampleFournisseurs = [
  { id: 1, nom: "Dell" },
  { id: 2, nom: "Office Plus" },
  { id: 3, nom: "Microsoft" },
];

function mockLoad(bons = sampleBons, lignes = sampleLignes) {
  api.get.mockImplementation((url) => {
    if (url === "/bons-commande") return Promise.resolve({ data: bons });
    if (url === "/ligne-budgets") return Promise.resolve({ data: lignes });
    if (url === "/fournisseurs")
      return Promise.resolve({ data: sampleFournisseurs });
    if (url === "/statuts-personnalises?type=bon_commande")
      return Promise.resolve({ data: [] });
    if (url === "/references/next/bon-commande")
      return Promise.resolve({ data: { reference: "BC-2027-009" } });
    return Promise.reject(new Error(`URL inattendue: ${url}`));
  });
}

function renderPage(initialEntry = "/consommation") {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <BonsCommande />
    </MemoryRouter>,
  );
}

describe("BonsCommande", () => {
  const showToast = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    useToast.mockReturnValue({ showToast });
    useAuth.mockReturnValue({ hasPermission: () => true, canViewAllDepartments: () => false, departements: [] });
  });

  it("affiche le chargement puis la liste des bons de commande", async () => {
    mockLoad();
    renderPage();

    expect(
      screen.getByText(/chargement des bons de commande/i),
    ).toBeInTheDocument();

    await waitFor(() =>
      expect(screen.getByText("BC-2026-001")).toBeInTheDocument(),
    );
    expect(screen.getByText("BC-2026-002")).toBeInTheDocument();
    expect(screen.getByText("2 bon(s) visible(s)")).toBeInTheDocument();
  });

  it("affiche le montant consommé et le lien vers les factures", async () => {
  mockLoad([{ ...sampleBons[0], montant_consomme: 7500, factures: [{ id: 1 }] }]);
  renderPage();

  // 1. Attendre que les nouvelles données soient appliquées au DOM (Reste : 20 000 - 7 500 = 12 500)
  // \. prend en compte le point comme séparateur de milliers (12.500)
  expect(await screen.findByText(/Reste\s*:\s*12\.500/i)).toBeInTheDocument();

  // 2. Vérifier ensuite le bouton factures (1)
  expect(screen.getByRole("button", { name: /factures \(1\)/i })).toBeInTheDocument();
});

  it("ajoute un statut avec sa couleur depuis le formulaire", async () => {
    mockLoad();
    api.post.mockResolvedValueOnce({ data: { id: 9, libelle: "En contrôle", couleur: "#123456" } });
    renderPage();
    await screen.findByText("BC-2026-001");

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouveau bon/i }));
    const dialog = await screen.findByRole("dialog");
    const statusField = within(dialog).getByText("Statut").closest(".form-field");
    await user.click(within(statusField).getByRole("button", { name: /ajouter/i }));
    await user.type(within(statusField).getByPlaceholderText("Nom"), "En contrôle");
    await user.click(within(statusField).getByRole("button", { name: /enregistrer/i }));

    await waitFor(() => expect(api.post).toHaveBeenCalledWith("/statuts-personnalises", { type: "bon_commande", libelle: "En contrôle", couleur: "#64748B" }));
    expect(within(statusField).getByRole("option", { name: "En contrôle" }).selected).toBe(true);
  });

  it("affiche un message si aucun bon n'est visible", async () => {
    mockLoad([]);
    renderPage();

    await waitFor(() =>
      expect(screen.getByText("Aucun bon de commande.")).toBeInTheDocument(),
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

  it("n'affiche pas le bouton Nouveau bon sans la permission bc.create", async () => {
    mockLoad();
    useAuth.mockReturnValue({ hasPermission: (code) => code !== "bc.create", canViewAllDepartments: () => false, departements: [] });
    renderPage();

    await waitFor(() =>
      expect(screen.getByText("BC-2026-001")).toBeInTheDocument(),
    );
    expect(
      screen.queryByRole("button", { name: /nouveau bon/i }),
    ).not.toBeInTheDocument();
  });

  it("filtre les bons par statut", async () => {
    mockLoad();
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BC-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    const [statutSelect] = screen.getAllByRole("combobox");
    await user.selectOptions(statutSelect, "envoye");

    expect(screen.queryByText("BC-2026-001")).not.toBeInTheDocument();
    expect(screen.getByText("BC-2026-002")).toBeInTheDocument();
    expect(screen.getByText("1 bon(s) visible(s)")).toBeInTheDocument();
  });

  it("filtre les bons par ligne budgétaire via l'URL", async () => {
    mockLoad();
    renderPage("/consommation?ligne=2");
    await waitFor(() =>
      expect(screen.getByText("BC-2026-002")).toBeInTheDocument(),
    );

    expect(screen.queryByText("BC-2026-001")).not.toBeInTheDocument();
    expect(screen.getByText("1 bon(s) visible(s)")).toBeInTheDocument();
  });

  it("ouvre la modale avec un numéro de BC généré automatiquement", async () => {
    mockLoad();
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BC-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouveau bon/i }));

    const dialog = await screen.findByRole("dialog");
    await waitFor(() =>
      expect(
        within(dialog).getByDisplayValue("BC-2027-009"),
      ).toBeInTheDocument(),
    );
  });

  it("crée un bon de commande et recharge la liste", async () => {
    mockLoad();
    api.post.mockResolvedValueOnce({});
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BC-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouveau bon/i }));
    const dialog = await screen.findByRole("dialog");

    const fullFields = dialog.querySelectorAll(".form-field.full");
    const otherFields = dialog.querySelectorAll(".form-field:not(.full)");

    await user.selectOptions(fullFields[0].querySelector("select"), "1");
    await user.type(fullFields[1].querySelector("input"), "Achat licences");
    await user.selectOptions(otherFields[2].querySelector("select"), "3"); // Fournisseur (Microsoft)
    await user.type(otherFields[5].querySelector("input"), "12000"); // Montant

    await user.click(
      within(dialog).getByRole("button", { name: /créer/i }),
    );

    await waitFor(() => expect(api.post).toHaveBeenCalledTimes(1));
    expect(api.post).toHaveBeenCalledWith(
      "/bons-commande",
      expect.objectContaining({
        ligne_budget_id: 1,
        intitule_bc: "Achat licences",
        fournisseur: "Microsoft",
        fournisseur_id: "3",
        montant_bc: 12000,
      }),
    );
    expect(showToast).toHaveBeenCalledWith(
      "Bon de commande créé au statut brouillon.",
    );
  });

  it("permet de modifier un bon en brouillon", async () => {
    mockLoad();
    api.put.mockResolvedValueOnce({});
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BC-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    const row = screen.getByText("BC-2026-001").closest("tr");
    await user.click(within(row).getByRole("button", { name: /modifier/i }));
    const dialog = await screen.findByRole("dialog");

    expect(
      within(dialog).getByRole("heading", { name: /modifier le bon/i }),
    ).toBeInTheDocument();
    await user.click(within(dialog).getByRole("button", { name: /enregistrer/i }));

    await waitFor(() =>
      expect(api.put).toHaveBeenCalledWith(
        "/bons-commande/100",
        expect.objectContaining({
          ligne_budget_id: 1,
          montant_bc: 20000,
          numero_bc: "BC-2026-001",
        }),
      ),
    );
    expect(showToast).toHaveBeenCalledWith("Bon de commande mis à jour.");
  });

  it("affiche les erreurs de validation (422) lors de la création", async () => {
    mockLoad();
    api.post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          errors: {
            ligne_budget_id: ["Le montant dépasse le disponible."],
          },
        },
      },
    });
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BC-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouveau bon/i }));
    const dialog = await screen.findByRole("dialog");

    const fullFields = dialog.querySelectorAll(".form-field.full");
    const otherFields = dialog.querySelectorAll(".form-field:not(.full)");
    await user.selectOptions(fullFields[0].querySelector("select"), "1");
    await user.type(fullFields[1].querySelector("input"), "Achat");
    await user.selectOptions(otherFields[2].querySelector("select"), "1");
    await user.type(otherFields[5].querySelector("input"), "999999");

    await user.click(
      within(dialog).getByRole("button", { name: /créer/i }),
    );

    expect(
      await within(dialog).findByText("Le montant dépasse le disponible."),
    ).toBeInTheDocument();
    expect(screen.getByRole("dialog")).toBeInTheDocument();
  });

  it("affiche le bouton Valider seulement pour les bons en brouillon", async () => {
    mockLoad();
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BC-2026-001")).toBeInTheDocument(),
    );

    const validerButtons = screen.getAllByRole("button", {
      name: /valider/i,
    });
    expect(validerButtons).toHaveLength(1);

    const row = screen.getByText("BC-2026-001").closest("tr");
    expect(within(row).getByRole("button", { name: /valider/i })).toBeInTheDocument();
  });

  it("ne propose pas Valider sans la permission bc.validate", async () => {
    mockLoad();
    useAuth.mockReturnValue({
      hasPermission: (code) => code !== "bc.validate",
      canViewAllDepartments: () => false,
      departements: [],
    });
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BC-2026-001")).toBeInTheDocument(),
    );

    expect(
      screen.queryByRole("button", { name: /valider/i }),
    ).not.toBeInTheDocument();
  });

  it("valide un bon de commande après confirmation", async () => {
    mockLoad();
    vi.spyOn(window, "confirm").mockReturnValue(true);
    api.post.mockResolvedValueOnce({});
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BC-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /valider/i }));

    await waitFor(() =>
      expect(api.post).toHaveBeenCalledWith("/bons-commande/100/validate"),
    );
    expect(showToast).toHaveBeenCalledWith("Bon de commande validé.");
  });

  it("n'appelle pas l'API si la validation n'est pas confirmée", async () => {
    mockLoad();
    vi.spyOn(window, "confirm").mockReturnValue(false);
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("BC-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /valider/i }));

    expect(api.post).not.toHaveBeenCalled();
  });
});