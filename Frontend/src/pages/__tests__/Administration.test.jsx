import { beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import Departements from "../Departements";
import Utilisateurs from "../Utilisateurs";
import ChangePassword from "../ChangePassword";
import Profil from "../Profil";
import api from "../../api/axios";
import { useAuth } from "../../context/AuthContext";
import { useToast } from "../../context/ToastContext";

vi.mock("../../api/axios", () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() } }));
vi.mock("../../context/AuthContext", () => ({ useAuth: vi.fn() }));
vi.mock("../../context/ToastContext", () => ({ useToast: vi.fn() }));
const navigate = vi.fn();
vi.mock("react-router-dom", async (original) => ({ ...(await original()), useNavigate: () => navigate }));

const toast = vi.fn();
const departments = [{ id: 1, nom: "Finance", created_at: "2026-01-01" }];
const currentUser = { id: 1, name: "Admin", email: "admin@example.test" };
const renderPage = (node) => render(<MemoryRouter>{node}</MemoryRouter>);

beforeEach(() => {
  vi.clearAllMocks();
  useToast.mockReturnValue({ showToast: toast });
  useAuth.mockReturnValue({ user: currentUser, departements: departments, departement: departments[0], permissions: [{ id: 1 }], hasPermission: () => true, changePassword: vi.fn(), logout: vi.fn() });
});

describe("Departements", () => {
  it("charge la liste et affiche l'état vide", async () => {
    api.get.mockResolvedValueOnce({ data: departments });
    renderPage(<Departements />);
    expect(screen.getByText(/chargement des départements/i)).toBeInTheDocument();
    expect(await screen.findByText("Finance")).toBeInTheDocument();
  });

  it("crée un département et recharge la liste", async () => {
    api.get.mockResolvedValue({ data: departments });
    api.post.mockResolvedValue({});
    renderPage(<Departements />);
    await screen.findByText("Finance");
    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouveau département/i }));
    await user.type(within(screen.getByRole("dialog")).getByRole("textbox"), "Achats");
    await user.click(within(screen.getByRole("dialog")).getByRole("button", { name: /enregistrer/i }));
    await waitFor(() => expect(api.post).toHaveBeenCalledWith("/departements", { nom: "Achats" }));
    expect(toast).toHaveBeenCalledWith("Département créé.");
  });

  it("affiche l'erreur de validation dans la modale", async () => {
    api.get.mockResolvedValue({ data: [] });
    api.post.mockRejectedValue({ response: { data: { errors: { nom: ["Nom requis"] } } } });
    renderPage(<Departements />);
    await screen.findByText(/aucun département/i);
    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /nouveau département/i }));
    await user.type(within(screen.getByRole("dialog")).getByRole("textbox"), "X");
    await user.click(within(screen.getByRole("dialog")).getByRole("button", { name: /enregistrer/i }));
    expect(await screen.findByText("Nom requis")).toBeInTheDocument();
  });
});

describe("Utilisateurs", () => {
  const users = [{ ...currentUser, is_active: true, departement: departments[0], permissions: [] }, { id: 2, name: "Lina", email: "lina@example.test", is_active: true, departement: departments[0], permissions: [{ id: 3, nom: "Voir" }] }];
  const load = () => api.get.mockImplementation((url) => Promise.resolve({ data: url === "/users" ? users : [{ id: 3, nom: "Voir" }, { id: 4, nom: "Éditer" }] }));
  it("crée un compte et transmet le département numérique", async () => {
    load(); api.post.mockResolvedValue({});
    renderPage(<Utilisateurs />); await screen.findByText("Lina");
    const user = userEvent.setup(); await user.click(screen.getByRole("button", { name: /nouvel utilisateur/i }));
    const dialog = screen.getByRole("dialog"); const inputs = dialog.querySelectorAll("input");
    await user.type(inputs[0], "Nora"); await user.type(inputs[1], "nora@example.test"); await user.type(inputs[2], "unmotdepasse12"); await user.type(inputs[3], "unmotdepasse12");
    await user.click(within(dialog).getByRole("button", { name: /créer/i }));
    await waitFor(() => expect(api.post).toHaveBeenCalledWith("/users", expect.objectContaining({ name: "Nora", departement_id: 1 })));
  });
  it("met à jour les permissions d'un autre utilisateur", async () => {
    load(); api.put.mockResolvedValue({}); renderPage(<Utilisateurs />); await screen.findByText("Lina");
    const user = userEvent.setup(); await user.click(screen.getAllByRole("button", { name: /permissions/i })[1]);
    const dialog = screen.getByRole("dialog");
    // Les permissions sont regroupées et repliées par défaut : il faut ouvrir le groupe avant de cocher.
    await user.click(within(dialog).getByRole("button", { name: /autres permissions/i }));
    await user.click(within(dialog).getAllByRole("checkbox")[2]);
    await user.click(within(dialog).getByRole("button", { name: /enregistrer/i }));
    await waitFor(() => expect(api.put).toHaveBeenCalledWith("/users/2/permissions", { permission_ids: [3, 4] }));
  });
  it("désactive un compte après confirmation", async () => {
    load(); api.patch.mockResolvedValue({}); vi.spyOn(window, "confirm").mockReturnValue(true); renderPage(<Utilisateurs />); await screen.findByText("Lina");
    await userEvent.setup().click(screen.getAllByRole("button", { name: /désactiver/i })[1]);
    await waitFor(() => expect(api.patch).toHaveBeenCalledWith("/users/2/status", { is_active: false }));
  });
});

describe("ChangePassword et Profil", () => {
  it("bloque un mot de passe trop court puis enregistre le nouveau", async () => {
    const changePassword = vi.fn().mockResolvedValue({}); useAuth.mockReturnValue({ changePassword, logout: vi.fn() }); renderPage(<ChangePassword />);
    const user = userEvent.setup(); const fields = screen.getAllByDisplayValue("");
    await user.type(fields[0], "ancien"); await user.type(fields[1], "court"); await user.type(fields[2], "court"); await user.click(screen.getByRole("button", { name: /mettre à jour/i }));
    expect(screen.getByText(/au moins 12 caractères/i)).toBeInTheDocument();
    await user.clear(fields[1]); await user.clear(fields[2]); await user.type(fields[1], "nouveaumotdepasse"); await user.type(fields[2], "nouveaumotdepasse"); await user.click(screen.getByRole("button", { name: /mettre à jour/i }));
    await waitFor(() => expect(changePassword).toHaveBeenCalledWith(expect.objectContaining({ current_password: "ancien" })));
    expect(navigate).toHaveBeenCalledWith("/", { replace: true });
  });
  it("affiche le profil et permet ses deux actions", async () => {
    const logout = vi.fn(); useAuth.mockReturnValue({ user: currentUser, departement: departments[0], permissions: [{ id: 1 }, { id: 2 }], logout }); renderPage(<Profil />);
    expect(screen.getByText("admin@example.test")).toBeInTheDocument();
    await userEvent.setup().click(screen.getByRole("button", { name: /modifier le mot de passe/i })); expect(navigate).toHaveBeenCalledWith("/change-password");
    await userEvent.setup().click(screen.getByRole("button", { name: /se déconnecter/i })); expect(logout).toHaveBeenCalled();
  });
});