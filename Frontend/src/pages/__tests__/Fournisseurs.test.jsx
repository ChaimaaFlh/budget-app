import { beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import Fournisseurs from "../Fournisseurs";
import api from "../../api/axios";
import { useAuth } from "../../context/AuthContext";
import { useToast } from "../../context/ToastContext";

vi.mock("../../api/axios", () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } }));
vi.mock("../../context/AuthContext", () => ({ useAuth: vi.fn() }));
vi.mock("../../context/ToastContext", () => ({ useToast: vi.fn() }));

describe("Fournisseurs", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useAuth.mockReturnValue({ hasPermission: () => true });
    useToast.mockReturnValue({ showToast: vi.fn() });
    api.get.mockImplementation((url) => {
      if (url === "/fournisseurs") return Promise.resolve({ data: [{ id: 4, nom: "Atlas Services", score: "87.50", bons_commande_count: 2 }] });
      if (url === "/fournisseurs/4") return Promise.resolve({ data: { fournisseur: { nom: "Atlas Services" }, score_moyen: 87.5, scores: [{ id: 1, annee: 2026, score: "90.00" }, { id: 2, annee: 2025, score: "85.00" }] } });
      return Promise.reject(new Error(`URL inattendue: ${url}`));
    });
  });

  it("affiche la moyenne des cinq dernières années et son historique", async () => {
    render(<Fournisseurs />);

    expect(await screen.findByText("87.50/100")).toBeInTheDocument();
    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /détail/i }));

    expect(await screen.findByRole("heading", { name: /historique des notes/i })).toBeInTheDocument();
    expect(screen.getByText("2026")).toBeInTheDocument();
    expect(screen.getByText("90.00/100")).toBeInTheDocument();
  });
});
