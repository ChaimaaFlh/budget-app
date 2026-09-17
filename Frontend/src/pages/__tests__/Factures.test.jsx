import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import Factures from "../Factures";
import api from "../../api/axios";
import { useAuth } from "../../context/AuthContext";
import { useToast } from "../../context/ToastContext";

vi.mock("../../api/axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));
vi.mock("../../context/AuthContext", () => ({ useAuth: vi.fn() }));
vi.mock("../../context/ToastContext", () => ({ useToast: vi.fn() }));
vi.mock("../../components/Factures/FactureDocumentsModal", () => ({
  default: ({ facture, onClose }) => (
    <div data-testid="docs-modal">
      Documents de {facture.ref_facture}
      <button onClick={onClose}>Fermer docs</button>
    </div>
  ),
}));

const sampleBons = [
  {
    id: 100,
    numero_bc: "BC-2026-001",
    intitule_bc: "Achat ordinateurs",
    montant_bc: 20000,
    ligne_budget_id: 1,
  },
  {
    id: 101,
    numero_bc: "BC-2026-002",
    intitule_bc: "Fournitures bureau",
    montant_bc: 5000,
    ligne_budget_id: 2,
  },
];

const facturesByBon = {
  100: [
    {
      id: 1,
      ref_facture: "FAC-2026-001",
      montant: 12000,
      date_reception: "2026-03-20",
      type_reglement: "acompte",
      statut: "validation",
      bon_commande_id: 100,
      documents_count: 0,
    },
  ],
  101: [
    {
      id: 2,
      ref_facture: "FAC-2026-002",
      montant: 5000,
      date_reception: "2026-04-05",
      type_reglement: "finale",
      statut: "reglee",
      bon_commande_id: 101,
      documents_count: 2,
    },
  ],
};

function mockLoad(bons = sampleBons, byBon = facturesByBon) {
  api.get.mockImplementation((url) => {
    if (url === "/bons-commande") return Promise.resolve({ data: bons });
    if (url === "/statuts-personnalises?type=facture") return Promise.resolve({ data: [] });
    const match = url.match(/^\/bons-commande\/(\d+)\/factures$/);
    if (match) return Promise.resolve({ data: byBon[match[1]] || [] });
    if (url === "/references/next/facture")
      return Promise.resolve({ data: { reference: "FAC-2027-777" } });
    return Promise.reject(new Error(`URL inattendue: ${url}`));
  });
}

function renderPage(initialEntry = "/factures") {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <Factures />
    </MemoryRouter>,
  );
}

describe("Factures", () => {
  const showToast = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    useToast.mockReturnValue({ showToast });
    useAuth.mockReturnValue({ hasPermission: () => true });
  });

  it("affiche le chargement puis la liste des factures", async () => {
    mockLoad();
    renderPage();

    expect(screen.getByText(/chargement des factures/i)).toBeInTheDocument();

    await waitFor(() =>
      expect(screen.getByText("FAC-2026-001")).toBeInTheDocument(),
    );
    expect(screen.getByText("FAC-2026-002")).toBeInTheDocument();
  });

  it("affiche un message si aucune facture n'est visible", async () => {
    mockLoad(sampleBons, { 100: [], 101: [] });
    renderPage();

    await waitFor(() =>
      expect(screen.getByText("Aucune facture visible.")).toBeInTheDocument(),
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

  it("n'affiche pas le bouton Nouvelle facture sans la permission facture.create", async () => {
    mockLoad();
    useAuth.mockReturnValue({
      hasPermission: (code) => code !== "facture.create",
    });
    renderPage();

    await waitFor(() =>
      expect(screen.getByText("FAC-2026-001")).toBeInTheDocument(),
    );
    expect(
      screen.queryByRole("button", { name: /nouvelle facture/i }),
    ).not.toBeInTheDocument();
  });

  it("cache le bouton Supprimer sans la permission facture.delete", async () => {
    mockLoad();
    useAuth.mockReturnValue({
      hasPermission: (code) => code !== "facture.delete",
    });
    renderPage();

    await waitFor(() =>
      expect(screen.getByText("FAC-2026-001")).toBeInTheDocument(),
    );
    expect(
      screen.queryByRole("button", { name: /supprimer/i }),
    ).not.toBeInTheDocument();
  });

  it("filtre les factures par ligne budgétaire via l'URL", async () => {
    mockLoad();
    renderPage("/factures?ligne=2");

    await waitFor(() =>
      expect(screen.getByText("FAC-2026-002")).toBeInTheDocument(),
    );
    expect(screen.queryByText("FAC-2026-001")).not.toBeInTheDocument();
    expect(
      screen.getByText("Factures filtrées pour une ligne budgétaire."),
    ).toBeInTheDocument();
  });

  it("affiche l'état complet ou partiel du montant facturé par rapport au bon", async () => {
    mockLoad();
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("FAC-2026-001")).toBeInTheDocument(),
    );

    const partialRow = screen.getByText("FAC-2026-001").closest("tr");
    expect(
      partialRow.querySelector(".invoice-pending"),
    ).toBeInTheDocument();

    const completeRow = screen.getByText("FAC-2026-002").closest("tr");
    expect(
      completeRow.querySelector(".invoice-complete"),
    ).toBeInTheDocument();
  });

  it("affiche le statut de chaque facture", async () => {
    mockLoad();
    renderPage();

    await waitFor(() =>
      expect(screen.getByText("FAC-2026-001")).toBeInTheDocument(),
    );
    expect(within(screen.getByText("FAC-2026-001").closest("tr")).getByText("Validation")).toBeInTheDocument();
    expect(within(screen.getByText("FAC-2026-002").closest("tr")).getByText("Réglée")).toBeInTheDocument();
  });

  it("ouvre la modale avec une référence générée automatiquement", async () => {
    mockLoad();
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("FAC-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(
      screen.getByRole("button", { name: /nouvelle facture/i }),
    );

    const dialog = await screen.findByRole("dialog");
    await waitFor(() =>
      expect(
        within(dialog).getByDisplayValue("FAC-2027-777"),
      ).toBeInTheDocument(),
    );
  });

  it("crée une facture et recharge la liste", async () => {
    mockLoad();
    api.post.mockResolvedValueOnce({});
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("FAC-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(
      screen.getByRole("button", { name: /nouvelle facture/i }),
    );
    const dialog = await screen.findByRole("dialog");

    const bonSelect = dialog
      .querySelectorAll(".form-field.full")[0]
      .querySelector("select");
    await user.selectOptions(bonSelect, "100");

    const otherFields = dialog.querySelectorAll(".form-field:not(.full)");
    const montantInput = otherFields[1].querySelector("input"); // Montant
    await user.type(montantInput, "8000");

    await user.click(
      within(dialog).getByRole("button", { name: /enregistrer/i }),
    );

    await waitFor(() => expect(api.post).toHaveBeenCalledTimes(1));
    expect(api.post).toHaveBeenCalledWith(
      "/bons-commande/100/factures",
      expect.objectContaining({
        ref_facture: "FAC-2027-777",
        montant: 8000,
        type_reglement: "finale",
      }),
    );
    expect(showToast).toHaveBeenCalledWith("Facture enregistrée.");
  });

  it("affiche l'erreur de validation (422) sur le champ bon de commande", async () => {
    mockLoad();
    api.post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          errors: { bon_commande_id: ["Ce bon de commande est clôturé."] },
        },
      },
    });
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("FAC-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(
      screen.getByRole("button", { name: /nouvelle facture/i }),
    );
    const dialog = await screen.findByRole("dialog");

    const bonSelect = dialog
      .querySelectorAll(".form-field.full")[0]
      .querySelector("select");
    await user.selectOptions(bonSelect, "100");
    const otherFields = dialog.querySelectorAll(".form-field:not(.full)");
    await user.type(otherFields[1].querySelector("input"), "999999");

    await user.click(
      within(dialog).getByRole("button", { name: /enregistrer/i }),
    );

    expect(
      await within(dialog).findByText("Ce bon de commande est clôturé."),
    ).toBeInTheDocument();
    expect(screen.getByRole("dialog")).toBeInTheDocument();
  });

  it("affiche l'erreur de validation du montant", async () => {
    mockLoad();
    api.post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          message: "Le montant dépasse le solde du bon.",
          errors: { montant: ["Le montant dépasse le solde du bon."] },
        },
      },
    });
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("FAC-2026-001")).toBeInTheDocument(),
    );

    const user = userEvent.setup();
    await user.click(
      screen.getByRole("button", { name: /nouvelle facture/i }),
    );
    const dialog = await screen.findByRole("dialog");

    const bonSelect = dialog
      .querySelectorAll(".form-field.full")[0]
      .querySelector("select");
    await user.selectOptions(bonSelect, "100");
    const otherFields = dialog.querySelectorAll(".form-field:not(.full)");
    await user.type(otherFields[1].querySelector("input"), "999999");

    await user.click(
      within(dialog).getByRole("button", { name: /enregistrer/i }),
    );

    expect(await within(dialog).findByText("Le montant dépasse le solde du bon.")).toBeInTheDocument();
  });

  it("supprime une facture après confirmation", async () => {
    mockLoad();
    vi.spyOn(window, "confirm").mockReturnValue(true);
    api.delete.mockResolvedValueOnce({});
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("FAC-2026-001")).toBeInTheDocument(),
    );

    const row = screen.getByText("FAC-2026-001").closest("tr");
    const user = userEvent.setup();
    await user.click(within(row).getByRole("button", { name: /supprimer/i }));

    await waitFor(() =>
      expect(api.delete).toHaveBeenCalledWith(
        "/bons-commande/100/factures/1",
      ),
    );
    expect(showToast).toHaveBeenCalledWith("Facture supprimée.");
  });

  it("n'appelle pas l'API si la suppression n'est pas confirmée", async () => {
    mockLoad();
    vi.spyOn(window, "confirm").mockReturnValue(false);
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("FAC-2026-001")).toBeInTheDocument(),
    );

    const row = screen.getByText("FAC-2026-001").closest("tr");
    const user = userEvent.setup();
    await user.click(within(row).getByRole("button", { name: /supprimer/i }));

    expect(api.delete).not.toHaveBeenCalled();
  });

  it("ouvre la modale de documents au clic sur le badge Documents", async () => {
    mockLoad();
    renderPage();
    await waitFor(() =>
      expect(screen.getByText("FAC-2026-001")).toBeInTheDocument(),
    );

    const row = screen.getByText("FAC-2026-001").closest("tr");
    const user = userEvent.setup();
    await user.click(within(row).getByRole("button", { name: /ajouter/i }));

    expect(await screen.findByTestId("docs-modal")).toHaveTextContent(
      "Documents de FAC-2026-001",
    );

    await user.click(screen.getByText("Fermer docs"));
    expect(screen.queryByTestId("docs-modal")).not.toBeInTheDocument();
  });
});
