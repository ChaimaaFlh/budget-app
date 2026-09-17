import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import { MemoryRouter, Routes, Route } from "react-router-dom";
import ProtectedRoute from "../ProtectedRoute";
import { useAuth } from "../../../context/AuthContext";
import { useToast } from "../../../context/ToastContext";

vi.mock("../../../context/AuthContext", () => ({ useAuth: vi.fn() }));
vi.mock("../../../context/ToastContext", () => ({ useToast: vi.fn() }));

function renderRoute({ initialEntry = "/prive" } = {}) {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <Routes>
        <Route path="/login" element={<div>Page Login</div>} />
        <Route path="/change-password" element={<div>Changer mot de passe</div>} />
        <Route path="/" element={<div>Accueil</div>} />
        <Route element={<ProtectedRoute requiredPermission="budget.view" />}>
          <Route path="/prive" element={<div>Page privée</div>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

describe("ProtectedRoute", () => {
  const showToast = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    useToast.mockReturnValue({ showToast });
  });

  it("affiche un loader pendant le chargement de la session", () => {
    useAuth.mockReturnValue({ loading: true });
    const { container } = renderRoute();
    expect(container.querySelector(".spinner")).toBeInTheDocument();
  });

  it("redirige vers /login si non authentifié", () => {
    useAuth.mockReturnValue({ loading: false, isAuthenticated: false });
    renderRoute();
    expect(screen.getByText("Page Login")).toBeInTheDocument();
  });

  it("redirige vers /change-password si le mot de passe doit être changé", () => {
    useAuth.mockReturnValue({
      loading: false,
      isAuthenticated: true,
      user: { must_change_password: true },
      hasPermission: () => true,
    });
    renderRoute();
    expect(screen.getByText("Changer mot de passe")).toBeInTheDocument();
  });

  it("redirige vers / et affiche un toast si la permission manque", () => {
    useAuth.mockReturnValue({
      loading: false,
      isAuthenticated: true,
      user: { must_change_password: false },
      hasPermission: () => false,
    });
    renderRoute();
    expect(screen.getByText("Accueil")).toBeInTheDocument();
    expect(showToast).toHaveBeenCalledWith(
      expect.stringContaining("budget.view"),
      true,
    );
  });

  it("affiche la page protégée si tout est en ordre", () => {
    useAuth.mockReturnValue({
      loading: false,
      isAuthenticated: true,
      user: { must_change_password: false },
      hasPermission: () => true,
    });
    renderRoute();
    expect(screen.getByText("Page privée")).toBeInTheDocument();
    expect(showToast).not.toHaveBeenCalled();
  });
});