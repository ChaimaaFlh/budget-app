import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor, act } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { AuthProvider, useAuth } from "../AuthContext";
import api from "../../api/axios";

vi.mock("../../api/axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}));

function Probe() {
  const auth = useAuth();
  return (
    <div>
      <span data-testid="loading">{String(auth.loading)}</span>
      <span data-testid="authed">{String(auth.isAuthenticated)}</span>
      <span data-testid="perm">{String(auth.hasPermission("budget.view"))}</span>
      <button onClick={() => auth.login("a@a.com", "secret")}>login</button>
      <button onClick={() => auth.logout()}>logout</button>
    </div>
  );
}

describe("AuthContext", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
  });

  it("ne charge pas de session si aucun token n'est stocké", async () => {
    render(<AuthProvider><Probe /></AuthProvider>);
    await waitFor(() => expect(screen.getByTestId("loading").textContent).toBe("false"));
    expect(screen.getByTestId("authed").textContent).toBe("false");
    expect(api.get).not.toHaveBeenCalled();
  });

  it("restaure la session si un token existe", async () => {
    localStorage.setItem("agma_token", "tok123");
    api.get.mockResolvedValueOnce({
      data: { user: { id: 1 }, permissions: ["budget.view"], departements: [] },
    });
    render(<AuthProvider><Probe /></AuthProvider>);
    await waitFor(() => expect(screen.getByTestId("authed").textContent).toBe("true"));
    expect(screen.getByTestId("perm").textContent).toBe("true");
  });

  it("supprime le token si la session est invalide", async () => {
    localStorage.setItem("agma_token", "expired");
    api.get.mockRejectedValueOnce(new Error("401"));
    render(<AuthProvider><Probe /></AuthProvider>);
    await waitFor(() => expect(screen.getByTestId("loading").textContent).toBe("false"));
    expect(localStorage.getItem("agma_token")).toBeNull();
    expect(screen.getByTestId("authed").textContent).toBe("false");
  });

  it("login stocke le token et recharge la session", async () => {
    api.post.mockResolvedValueOnce({ data: { token: "newtok" } });
    api.get.mockResolvedValueOnce({
      data: { user: { id: 2 }, permissions: [], departements: [] },
    });
    render(<AuthProvider><Probe /></AuthProvider>);
    await waitFor(() => expect(screen.getByTestId("loading").textContent).toBe("false"));

    const user = userEvent.setup();
    await act(async () => {
      await user.click(screen.getByText("login"));
    });

    expect(api.post).toHaveBeenCalledWith("/login", { email: "a@a.com", password: "secret" });
    expect(localStorage.getItem("agma_token")).toBe("newtok");
    await waitFor(() => expect(screen.getByTestId("authed").textContent).toBe("true"));
  });

  it("logout nettoie le token même si l'appel API échoue", async () => {
    localStorage.setItem("agma_token", "tok123");
    api.get.mockResolvedValueOnce({
      data: { user: { id: 1 }, permissions: [], departements: [] },
    });
    api.post.mockRejectedValueOnce(new Error("network error"));
    render(<AuthProvider><Probe /></AuthProvider>);
    await waitFor(() => expect(screen.getByTestId("authed").textContent).toBe("true"));

    const user = userEvent.setup();
    await act(async () => {
      await user.click(screen.getByText("logout"));
    });

    expect(localStorage.getItem("agma_token")).toBeNull();
    expect(screen.getByTestId("authed").textContent).toBe("false");
  });
});