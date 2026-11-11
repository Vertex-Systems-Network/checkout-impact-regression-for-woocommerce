# Baselines and Comparison

## Recording values

Each field has a state:

| State | Use it when |
|---|---|
| Observed | You saw the value at checkout. Money: `7.50`. Shipping: `flat_rate:3`. Payment: tick gateways or type IDs like `bacs,stripe`. |
| Confirmed missing | You checked and the option is not offered. |
| Not recorded / Not supported here / Could not check | You did not or could not check. The field is reported as **Needs review**. |

Values that look like email addresses, passwords, tokens or API keys are rejected.

## Baselines

A baseline is the approved, known-good state of a scenario version. To approve, every After field must be *Observed* or *Confirmed missing*. Approving again creates a new **revision**; older revisions stay in Scenarios → *Baselines*.

## Results

| Result | Meaning |
|---|---|
| Changed | Value differs from Before |
| Missing after change | It existed before, now it is gone |
| Newly present | It was missing before, now it appears |
| Unchanged | Identical |
| Needs review | One side was not recorded or cannot be compared – never treated as unchanged |

Money is compared exactly (no rounding) in the scenario currency.
