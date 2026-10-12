/** The `plan_locked` payload ({ features, plan, agency }) of a 403 from a section the plan doesn't include, else null. */
export function planLocked(error) {
    return (error?.response?.status === 403 && error.response.data?.plan_locked) || null;
}
