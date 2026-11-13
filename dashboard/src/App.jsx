import { Suspense, lazy } from 'react'
import { Link, Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { useAuth } from './auth/AuthContext.jsx'
import Layout from './components/Layout.jsx'
import { Loading } from './components/ui.jsx'
import EnquiryList from './pages/EnquiryList.jsx'
import Login from './pages/Login.jsx'

// Route-level code splitting: the chart library only loads with the Overview page.
const Overview = lazy(() => import('./pages/Overview.jsx'))
const EnquiryDetail = lazy(() => import('./pages/EnquiryDetail.jsx'))
const Clinics = lazy(() => import('./pages/Clinics.jsx'))
const Treatments = lazy(() => import('./pages/Treatments.jsx'))
const Users = lazy(() => import('./pages/Users.jsx'))

const page = (element) => <Suspense fallback={<Loading />}>{element}</Suspense>

function RequireAuth({ children, adminOnly = false }) {
  const { status, isAdmin } = useAuth()
  const location = useLocation()
  if (status === 'loading') {
    return (
      <main className="main">
        <Loading label="Restoring your session…" />
      </main>
    )
  }
  if (status !== 'signed-in') {
    return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />
  }
  if (adminOnly && !isAdmin) return <NotFound />
  return children
}

function NotFound() {
  return (
    <>
      <h1>Page not found</h1>
      <p>
        <Link to="/">Go to the overview</Link>
      </p>
    </>
  )
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        element={
          <RequireAuth>
            <Layout />
          </RequireAuth>
        }
      >
        <Route index element={page(<Overview />)} />
        <Route path="enquiries" element={<EnquiryList />} />
        <Route path="enquiries/:id" element={page(<EnquiryDetail />)} />
        <Route path="appointments" element={<EnquiryList appointmentsOnly key="appointments" />} />
        <Route path="clinics" element={page(<Clinics />)} />
        <Route path="treatments" element={page(<Treatments />)} />
        <Route
          path="users"
          element={
            <RequireAuth adminOnly>{page(<Users />)}</RequireAuth>
          }
        />
        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  )
}
