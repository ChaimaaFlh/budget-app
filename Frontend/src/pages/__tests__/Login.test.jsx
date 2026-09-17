import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor, act } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter, Routes, Route } from "react-router-dom";
import Login from "../Login";
import { useAuth } from "../../context/AuthContext";
import { useToast } from "../../context/ToastContext";

vi.mock("../../context/AuthContext", () => ({ useAuth: vi.fn() }));
vi.mock("../../context/ToastContext", () => ({ useToast: vi.fn() }));
vi.mock("../../assets/agma_logo.png", () => ({ default: "logo.png" }));

function renderLogin() {
  return render(
    <MemoryRouter initialEntries={["/login"]}>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/" element={<div>Tableau de bord</div>} />
        <Route path="/change-password" element={<div>Changer mot de passe</div>} />
      </Routes>
    </MemoryRouter>,
  );
}

describe("Login", () => {
  const login = vi.fn();
  const showToast = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    useAuth.mockReturnValue({ login });
    useToast.mockReturnValue({ showToast });
  });

  it("affiche des erreurs de validation si les champs sont vides", async () => {
    renderLogin();
    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /se connecter/i }));

    expect(await screen.findByText("Email requis.")).toBeInTheDocument();
    expect(screen.getByText("Mot de passe requis.")).toBeInTheDocument();
    expect(login).not.toHaveBeenCalled();
  });

  it("se connecte et redirige vers l'accueil en cas de succès", async () => {
    login.mockResolvedValueOnce({ must_change_password: false });
    renderLogin();
    const user = userEvent.setup();

    await user.type(screen.getByPlaceholderText(/admin@agma.ma/i), "admin@agma.ma");
    await user.type(screen.getByPlaceholderText("********"), "motdepasse");
    await user.click(screen.getByRole("button", { name: /se connecter/i }));

    expect(login).toHaveBeenCalledWith("admin@agma.ma", "motdepasse");
    await waitFor(() => expect(screen.getByText("Tableau de bord")).toBeInTheDocument());
    expect(showToast).toHaveBeenCalledWith("Connexion réussie !");
  });

  it("redirige vers /change-password si le mot de passe doit être changé", async () => {
    login.mockResolvedValueOnce({ must_change_password: true });
    renderLogin();
    const user = userEvent.setup();

    await user.type(screen.getByPlaceholderText(/admin@agma.ma/i), "admin@agma.ma");
    await user.type(screen.getByPlaceholderText("********"), "motdepasse");
    await user.click(screen.getByRole("button", { name: /se connecter/i }));

    await waitFor(() =>
      expect(screen.getByText("Changer mot de passe")).toBeInTheDocument(),
    );
  });

  it("affiche les erreurs de champs renvoyées par le serveur (422)", async () => {
    login.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { message: "Identifiants incorrect.", errors: { email: ["Email invalide."] } },
      },
    });
    renderLogin();
    const user = userEvent.setup();

    await user.type(screen.getByPlaceholderText(/admin@agma.ma/i), "bad");
    await user.type(screen.getByPlaceholderText("********"), "x");
    await user.click(screen.getByRole("button", { name: /se connecter/i }));

    expect(await screen.findByText("Email invalide.")).toBeInTheDocument();
    expect(screen.getByText("Identifiants incorrect.")).toBeInTheDocument();
  });

  it("affiche une erreur générique en cas d'échec serveur (500)", async () => {
    login.mockRejectedValueOnce({ response: { status: 500 } });
    renderLogin();
    const user = userEvent.setup();

    await user.type(screen.getByPlaceholderText(/admin@agma.ma/i), "a@a.com");
    await user.type(screen.getByPlaceholderText("********"), "x");
    await user.click(screen.getByRole("button", { name: /se connecter/i }));

    expect(
      await screen.findByText("Erreur serveur. Veuillez réessayer plus tard."),
    ).toBeInTheDocument();
  });

  it("désactive le bouton pendant la soumission", async () => {
    let resolveLogin;
    login.mockReturnValueOnce(new Promise((r) => (resolveLogin = r)));
    renderLogin();
    const user = userEvent.setup();

    await user.type(screen.getByPlaceholderText(/admin@agma.ma/i), "a@a.com");
    await user.type(screen.getByPlaceholderText("********"), "x");
    await user.click(screen.getByRole("button", { name: /se connecter/i }));

    expect(screen.getByRole("button", { name: /connexion.../i })).toBeDisabled();

    await act(async () => {
      resolveLogin({ must_change_password: false });
      await Promise.resolve();
    });
  });
});