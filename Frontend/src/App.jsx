import { BrowserRouter, Navigate, Routes, Route } from "react-router-dom";
import { AuthProvider } from "./context/AuthContext";
import { ToastProvider } from "./context/ToastContext";
import ProtectedRoute from "./components/Auth/ProtectedRoute";
import Layout from "./components/Layout/Layout";
import Login from "./pages/Login";
import Dashboard from "./pages/Dashboard";
import ChangePassword from "./pages/ChangePassword";
import ArborescenceBudgetaire from "./pages/ArborescenceBudgetaire";
import Utilisateurs from "./pages/Utilisateurs";
import Departements from "./pages/Departements";
import LignesBudgetaires from "./pages/LignesBudgetaires";
import Budgets from "./pages/Budgets";
import BonsCommande from "./pages/BonsCommande";
import Factures from "./pages/Factures";
import Rapports from "./pages/Rapports";
import DetailLigneBudgetaire from "./pages/DetailLigneBudgetaire";
import Profil from "./pages/Profil";
import Fournisseurs from "./pages/Fournisseurs";
import Categories from "./pages/Categories";

// Pages à construire dans les prochaines étapes
function _Placeholder({ title }) {
  return (
    <div>
      <div className="eyebrow">À venir</div>
      <h1>{title}</h1>
      <p style={{ color: "var(--text-muted)", marginTop: 8 }}>
        Cette page sera implémentée à l'étape suivante.
      </p>
    </div>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <ToastProvider>
          <Routes>
            <Route path="/login" element={<Login />} />

            <Route element={<ProtectedRoute allowPasswordChange />}>
              <Route path="/change-password" element={<ChangePassword />} />
            </Route>

            <Route element={<ProtectedRoute />}>
              <Route element={<Layout />}>
                <Route path="/" element={<Dashboard />} />
                <Route
                  path="/arborescence"
                  element={<ArborescenceBudgetaire readOnly />}
                />
                <Route path="/budgets" element={<Budgets />} />
                <Route path="/lignes" element={<LignesBudgetaires />} />
                <Route path="/lignes/:id" element={<DetailLigneBudgetaire />} />
                <Route path="/bons" element={<BonsCommande />} />
                <Route path="/factures" element={<Factures />} />
                <Route path="/fournisseurs" element={<Fournisseurs />} />
                <Route path="/rapports" element={<Rapports />} />
                <Route path="/profil" element={<Profil />} />
              </Route>
            </Route>

            <Route
              element={
                <ProtectedRoute requiredPermission="departement.view_all" />
              }
            >
              <Route element={<Layout />}>
                <Route path="/departements" element={<Departements />} />
              </Route>
            </Route>

            <Route
              element={
                <ProtectedRoute requiredPermission="user.manage_permissions" />
              }
            >
              <Route element={<Layout />}>
                <Route path="/utilisateurs" element={<Utilisateurs />} />
                <Route path="/categories" element={<Categories />} />
              </Route>
            </Route>
          </Routes>
        </ToastProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}
