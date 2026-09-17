import { useEffect, useMemo, useState } from "react";
import { useAuth } from "../context/AuthContext";
import { useToast } from "../context/ToastContext";
import api from "../api/axios";
import "../styles/Dashboard.css";

const fmtMAD = (n) =>
  new Intl.NumberFormat("fr-MA", {
    style: "currency",
    currency: "MAD",
    maximumFractionDigits: 0,
  }).format(n || 0);

const KPI_ICON_PATHS = {
  budget: (
    <>
      <path d="M3 12a9 9 0 1018 0 9 9 0 00-18 0z" />
      <path d="M12 7v5l3 3" />
    </>
  ),
  engage: (
    <>
      <path d="M6 3h9l5 5v13a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z" />
      <path d="M9 12h6M9 16h6" />
    </>
  ),
  solde: <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" />,
  depassement: (
    <path d="M12 9v4M12 17h.01M10.3 3.9L2.7 18a2 2 0 001.8 3h15a2 2 0 001.8-3L13.7 3.9a2 2 0 00-3.4 0z" />
  ),
};

function KpiIcon({ name, tone }) {
  return (
    <div className={`kpi-icon tone-${tone}`}>
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
      >
        {KPI_ICON_PATHS[name]}
      </svg>
    </div>
  );
}

export default function Dashboard() {
  const { departement, departements, canViewAllDepartments } = useAuth();
  const { showToast } = useToast();

  const [budgets, setBudgets] = useState([]);
  const [lignes, setLignes] = useState([]);
  const [bons, setBons] = useState([]);
  const [sousCategories, setSousCategories] = useState([]);
  const [loading, setLoading] = useState(true);

  // Filtre département : uniquement exploitable si l'utilisateur a departement.view_all
  const [selectedDept, setSelectedDept] = useState("all");

  useEffect(() => {
    (async () => {
      try {
        const [{ data: b }, { data: l }, { data: bc }, { data: sc }] =
          await Promise.all([
            api.get("/budgets"),
            api.get("/ligne-budgets"),
            api.get("/bons-commande"),
            api.get("/sous-categories"),
          ]);
        setBudgets(b);
        setLignes(l);
        setBons(bc);
        setSousCategories(sc);
      } catch {
        showToast("Impossible de charger le tableau de bord.", true);
      } finally {
        setLoading(false);
      }
    })();
  }, [showToast]);

  // Le backend renvoie déjà uniquement les lignes/BC autorisés (scopeVisibleParUtilisateur).
  // Ce filtre ne fait que trier côté client parmi ce que l'API a déjà autorisé à voir.
  const deptFilterActive = canViewAllDepartments() && selectedDept !== "all";
  const lignesFiltrees = useMemo(
    () =>
      deptFilterActive
        ? lignes.filter((l) => String(l.departement_id) === selectedDept)
        : lignes,
    [lignes, deptFilterActive, selectedDept],
  );
  const bonsFiltres = useMemo(
    () =>
      deptFilterActive
        ? bons.filter((b) => String(b.departement_id) === selectedDept)
        : bons,
    [bons, deptFilterActive, selectedDept],
  );

  // sous_categorie_id -> nom de la catégorie parente (pour le graphique)
  const categorieParSousCat = useMemo(() => {
    const map = {};
    sousCategories.forEach((sc) => {
      map[sc.id] = sc.categorie?.nom || "Sans catégorie";
    });
    return map;
  }, [sousCategories]);

  // ligne_budget_id -> ligne (fallback fiable, indépendant des relations eager-loadées côté API)
  const ligneParId = useMemo(
    () => Object.fromEntries(lignes.map((l) => [l.id, l])),
    [lignes],
  );

  const kpis = useMemo(() => {
    const totalAlloue = lignesFiltrees.reduce(
      (s, l) => s + Number(l.montant_alloue || 0),
      0,
    );
    const totalConsomme = lignesFiltrees.reduce(
      (s, l) => s + Number(l.total_consomme || 0),
      0,
    );
    const totalDisponible = totalAlloue - totalConsomme;
    const nbDepassement = lignesFiltrees.filter(
      (l) => l.statut === "Dépassement",
    ).length;
    const pctConsomme = totalAlloue
      ? Math.round((totalConsomme / totalAlloue) * 100)
      : 0;
    const pctDisponible = totalAlloue
      ? Math.round((totalDisponible / totalAlloue) * 100)
      : 0;

    return {
      totalAlloue,
      totalConsomme,
      totalDisponible,
      nbDepassement,
      pctConsomme,
      pctDisponible,
    };
  }, [lignesFiltrees]);

  // Consommation par catégorie (graphique en barres)
  const chartData = useMemo(() => {
    const map = {};
    lignesFiltrees.forEach((l) => {
      const nom = categorieParSousCat[l.sous_categorie_id] || "Sans catégorie";
      if (!map[nom]) map[nom] = { alloue: 0, consomme: 0 };
      map[nom].alloue += Number(l.montant_alloue || 0);
      map[nom].consomme += Number(l.total_consomme || 0);
    });
    const maxVal = Math.max(...Object.values(map).map((v) => v.alloue), 1);
    return Object.entries(map)
      .slice(0, 6)
      .map(([nom, v]) => ({
        nom,
        ...v,
        pctAlloue: (v.alloue / maxVal) * 100,
        pctConsomme: (v.consomme / maxVal) * 100,
        pctInterne: v.alloue ? Math.round((v.consomme / v.alloue) * 100) : 0,
      }));
  }, [lignesFiltrees, categorieParSousCat]);

  // Lignes à surveiller (dépassement ou totalement consommées)
  const lignesASurveiller = useMemo(
    () =>
      lignesFiltrees
        .filter(
          (l) =>
            l.statut === "Dépassement" || l.statut === "Totalement Consommé",
        )
        .slice(0, 5),
    [lignesFiltrees],
  );

  // Derniers bons de commande : brouillons d'abord, puis envoyés.
  const bonsRecents = useMemo(() => {
    const brouillons = bonsFiltres.filter((b) => b.statut === "brouillon");
    const envoyes = bonsFiltres.filter((b) => b.statut === "envoye");
    return [...brouillons, ...envoyes].slice(0, 6);
  }, [bonsFiltres]);

  if (loading) {
    return (
      <div
        style={{ display: "flex", justifyContent: "center", padding: "60px 0" }}
      >
        <span
          className="spinner"
          style={{
            borderTopColor: "var(--navy-900)",
            borderColor: "var(--border)",
          }}
        />
      </div>
    );
  }

  return (
    <div className="dashboard">
      <div className="dashboard-header">
        <div>
          <h1>Tableau de bord</h1>
          <p className="dashboard-sub">
            {canViewAllDepartments()
              ? "Aperçu budgétaire consolidé de tous les départements."
              : `Aperçu budgétaire du département ${departement?.nom || "—"}.`}
          </p>
        </div>

        {canViewAllDepartments() && (
          <div className="scope-control">
            <span className="badge neutral">
              Administrateur — accès tous départements
            </span>
            <select
              className="dept-select"
              value={selectedDept}
              onChange={(e) => setSelectedDept(e.target.value)}
            >
              <option value="all">Tous les départements</option>
              {departements.map((d) => (
                <option key={d.id} value={String(d.id)}>
                  {d.nom}
                </option>
              ))}
            </select>
          </div>
        )}
      </div>

      <div className="kpi-grid">
        <div className="kpi-card">
          <div className="kpi-top">
            <span className="kpi-label">Budget alloué (périmètre visible)</span>
            <KpiIcon name="budget" tone="navy" />
          </div>
          <div className="kpi-value">{fmtMAD(kpis.totalAlloue)}</div>
          <div className="kpi-foot">
            {lignesFiltrees.length} ligne(s) budgétaire(s)
          </div>
        </div>

        <div className="kpi-card">
          <div className="kpi-top">
            <span className="kpi-label">Montant engagé (BC)</span>
            <KpiIcon name="engage" tone="success" />
          </div>
          <div className="kpi-value">{fmtMAD(kpis.totalConsomme)}</div>
          <div className="kpi-foot up">
            {kpis.pctConsomme}% consommé — {bonsFiltres.length} bon(s)
          </div>
        </div>

        <div className="kpi-card">
          <div className="kpi-top">
            <span className="kpi-label">Solde disponible</span>
            <KpiIcon name="solde" tone="navy" />
          </div>
          <div className="kpi-value">{fmtMAD(kpis.totalDisponible)}</div>
          <div className="kpi-foot">{kpis.pctDisponible}% disponible</div>
        </div>

        <div className="kpi-card">
          <div className="kpi-top">
            <span className="kpi-label">Lignes en dépassement</span>
            <KpiIcon name="depassement" tone="danger" />
          </div>
          <div className="kpi-value">{kpis.nbDepassement}</div>
          <div className="kpi-foot warn">à traiter en priorité</div>
        </div>
      </div>

      <div className="grid-2">
        <section className="panel">
          <div className="panel-head">
            <h3>Consommation par catégorie</h3>
          </div>
          <div className="panel-body">
            {chartData.length ? (
              <>
                <div className="chart">
                  {chartData.map((c) => (
                    <div className="chart-col" key={c.nom}>
                      <div className="chart-bars">
                        <div
                          className="bar alloue"
                          style={{ height: `${Math.max(c.pctAlloue, 2)}px` }}
                        />
                        <div
                          className="bar consomme"
                          style={{ height: `${Math.max(c.pctConsomme, 2)}px` }}
                        />
                      </div>
                      <div className="chart-col-label">{c.nom}</div>
                      <div className="chart-col-pct">{c.pctInterne}%</div>
                    </div>
                  ))}
                </div>
                <div className="legend">
                  <div className="legend-item">
                    <span
                      className="legend-dot"
                      style={{ background: "var(--navy-100)" }}
                    />
                    Budget alloué
                  </div>
                  <div className="legend-item">
                    <span
                      className="legend-dot"
                      style={{ background: "var(--navy-700)" }}
                    />
                    Montant consommé (BC)
                  </div>
                </div>
              </>
            ) : (
              <p className="empty-note">
                Aucune donnée visible dans votre périmètre.
              </p>
            )}
          </div>
        </section>

        <section className="panel">
          <div className="panel-head">
            <h3>Lignes à surveiller</h3>
          </div>
          <div className="panel-body">
            {lignesASurveiller.length ? (
              lignesASurveiller.map((l) => {
                const alloue = Number(l.montant_alloue || 0);
                const consomme = Number(l.total_consomme || 0);
                const pct = alloue ? (consomme / alloue) * 100 : 0;
                return (
                  <div className="prog-row" key={l.id}>
                    <div className="prog-top">
                      <span className="prog-name">{l.intitule}</span>
                      <span className="prog-pct">{l.statut}</span>
                    </div>
                    <div className="prog-track">
                      <div
                        className="prog-fill"
                        style={{
                          width: `${Math.min(100, pct)}%`,
                          background:
                            l.statut === "Dépassement"
                              ? "var(--danger)"
                              : "var(--warning)",
                        }}
                      />
                    </div>
                    <div className="prog-sub">
                      {l.sous_categorie?.nom || ""} — {fmtMAD(consomme)} /{" "}
                      {fmtMAD(alloue)}
                    </div>
                  </div>
                );
              })
            ) : (
              <p className="empty-note">Aucune ligne à surveiller.</p>
            )}
          </div>
        </section>
      </div>

      <section className="panel">
        <div className="panel-head">
          <h3>Derniers bons de commande à valider ou en brouillon</h3>
        </div>
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>N° BC</th>
                <th>Intitulé</th>
                <th>Fournisseur</th>
                <th>Ligne</th>
                <th className="num">Montant</th>
                <th>Date</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              {bonsRecents.length ? (
                bonsRecents.map((b) => (
                  <tr key={b.id}>
                    <td className="mono">{b.numero_bc}</td>
                    <td>{b.intitule_bc}</td>
                    <td>{b.fournisseur}</td>
                    <td className="mono">
                      {ligneParId[b.ligne_budget_id]?.code || "—"}
                    </td>
                    <td className="num">{fmtMAD(b.montant_bc)}</td>
                    <td>
                      {b.date_achat
                        ? new Date(b.date_achat).toLocaleDateString("fr-FR")
                        : "—"}
                    </td>
                    <td>
                      <span
                        className={`badge ${b.statut === "envoye" ? "success" : "warning"}`}
                      >
                        {b.statut}
                      </span>
                    </td>
                  </tr>
                ))
              ) : (
                <tr className="empty-row">
                  <td colSpan={7}>Aucun bon visible dans votre périmètre.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}
